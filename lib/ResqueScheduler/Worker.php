<?php

/**
 * ResqueScheduler worker to handle scheduling of delayed tasks.
 *
 * @package          ResqueScheduler
 * @author           Chris Boulton <chris@bigcommerce.com>
 * @copyright    (c) 2012 Chris Boulton
 * @license          http://www.opensource.org/licenses/mit-license.php
 */
class ResqueScheduler_Worker
{
    /**
     * @var Psr\Log\LoggerInterface Logging object that impliments the PSR-3 LoggerInterface
     */
    private $logger;

    /**
     * @var boolean True if on the next iteration, the worker should shutdown.
     */
    private $shutdown = false;

    /**
     * @var boolean True if this worker is paused.
     */
    private $paused = false;

    /**
     * @var int Current log level of this worker.
     */
    public $logLevel = 0;

    /**
     * @var double Interval to sleep for between checking schedules.
     */
    protected $interval = 5;

    public function __construct()
    {
        $this->logger = new Resque_Log();
    }

    /**
     * Inject the logging object into the worker
     *
     * @param Psr\Log\LoggerInterface $logger
     */
    public function setLogger(Psr\Log\LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * The primary loop for a worker.
     *
     * Every $interval (seconds), the scheduled queue will be checked for jobs
     * that should be pushed to Resque.
     *
     * @param int $interval How often to check schedules.
     */
    public function work($interval = null)
    {
        if ($interval !== null) {
            $this->interval = $interval;
        }

        $this->updateProcLine('Starting');
        $this->registerSigHandlers();

        while (true) {
            pcntl_signal_dispatch();
            if ($this->shutdown) {
                break;
            }

            if ($this->paused) {
                $this->updateProcLine('Paused');
            } else {
                $this->handleDelayedItems();
            }

            $this->shutdown or $this->sleep();
        }
    }

    /**
     * Signal handler callback for USR2, pauses processing of new jobs.
     */
    public function pauseProcessing()
    {
        $this->logger->info('USR2 received; pausing job processing');
        $this->paused = true;
    }

    /**
     * Signal handler callback for CONT, resumes worker allowing it to pick
     * up new jobs.
     */
    public function unPauseProcessing()
    {
        $this->logger->info('CONT received; resuming job processing');
        $this->paused = false;
    }

    /**
     * Schedule a worker for shutdown. Will finish processing the current job
     * and when the timeout interval is reached, the worker will shut down.
     */
    public function shutdown()
    {
        $this->shutdown = true;
        $this->logger->info('Shutting down');
    }


    /**
     * Handle delayed items for the next scheduled timestamp.
     *
     * Searches for any items that are due to be scheduled in Resque
     * and adds them to the appropriate job queue in Resque.
     *
     * @param DateTime|int $timestamp Search for any items up to this timestamp to schedule.
     */
    private function handleDelayedItems($timestamp = null)
    {
        while (($oldestJobTimestamp = ResqueScheduler::nextDelayedTimestamp($timestamp)) !== false) {
            $this->updateProcLine('Processing Delayed Items');
            $this->enqueueDelayedItemsForTimestamp($oldestJobTimestamp);
            if ($this->shutdown || $this->paused) {
                break;
            }
        }
    }

    /**
     * Schedule all of the delayed jobs for a given timestamp.
     *
     * Searches for all items for a given timestamp, pulls them off the list of
     * delayed jobs and pushes them across to Resque.
     *
     * @param DateTime|int $timestamp Search for any items up to this timestamp to schedule.
     */
    private function enqueueDelayedItemsForTimestamp($timestamp)
    {
        $item = null;
        while ($item = ResqueScheduler::nextItemForTimestamp($timestamp)) {
            $this->logger->log(
                Psr\Log\LogLevel::INFO,
                'Queueing {class} in {queue} [delayed]',
                [
                    'class' => $item['class'],
                    'queue' => $item['queue'],
                ]
            );

            Resque_Event::trigger('beforeDelayedEnqueue', [
                'queue' => $item['queue'],
                'class' => $item['class'],
                'args'  => $item['args'],
            ]);

            Resque::enqueue($item['queue'], $item['class'], $item['args'][0], true);

            pcntl_signal_dispatch();
            if ($this->shutdown || $this->paused) {
                break;
            }
        }
    }

    /**
     * Sleep for the defined interval.
     */
    private function sleep()
    {
        usleep($this->interval * 1000000);
    }

    /**
     * Update the status of the current worker process.
     *
     * On supported systems (with the PECL proctitle module installed), update
     * the name of the currently running process to indicate the current state
     * of a worker.
     *
     * @param string $status The updated process title.
     */
    private function updateProcLine($status)
    {
        if (function_exists('setproctitle')) {
            setproctitle('resque-scheduler-' . ResqueScheduler::VERSION . ': ' . $status);
        }
    }

    /**
     * Register signal handlers that a worker should respond to.
     *
     * TERM: Shutdown after the current job finishes processing.
     * INT: Shutdown immediately and stop processing jobs.
     * QUIT: Shutdown after the current job finishes processing.
     * USR1: Kill the forked child immediately and continue processing jobs.
     */
    private function registerSigHandlers()
    {
        pcntl_signal(SIGTERM, [$this, 'shutdown']);
        pcntl_signal(SIGINT, [$this, 'shutdown']);
        pcntl_signal(SIGQUIT, [$this, 'shutdown']);
        pcntl_signal(SIGUSR1, [$this, 'shutdown']);
        pcntl_signal(SIGUSR2, [$this, 'pauseProcessing']);
        pcntl_signal(SIGCONT, [$this, 'unPauseProcessing']);
        $this->logger->notice('Signals are registered');
    }

}
