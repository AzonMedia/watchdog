<?php
declare(strict_types=1);

namespace Azonmedia\Watchdog;

use Azonmedia\Watchdog\Interfaces\BackendInterface;
use Guzaba2\Kernel\Kernel;
use Psr\Log\LogLevel;

class Watchdog
{    
    const HEART_BEAT_MILISECONDS = 1000;
    const CHECK_WORKER_STATUS_MILISECONDS = 1000;
    const KILL_WORKER_AFTER_NO_RESPONCE_SECONDS = 5;
    
    private $Backend;
    
    public function __construct(BackendInterface $Backend)
    {
        $this->Backend = $Backend;
        
    }
    
    public function checkin(\Swoole\Http\Server $Server, int $worker_id) 
    {   
        $this->Backend->checkin($Server->worker_pid, $worker_id);
    }
    
    public function check(int $worker_id)
    {
        $this->Backend->check($worker_id);
    }
    
    public static function kill_worker(int $worker_pid, int $worker_id)
    {
        // Send reload signal to a worker process.
        // swoole_process::kill($worker_pid, SIGUSR1);
        // If the process does not exit after self::KILL_WORKER_TIMER_TIME seconds try to kill it.
        // Timer::add(self::KILL_WORKER_TIMER_TIME, array('\swoole_process','kill'), array($worker_pid, SIGKILL), false);
        
        \swoole_process::kill($worker_pid, SIGKILL);
        Kernel::log(sprintf('Worker with id %s not responding more than %s seconds. Worker process id %s killed.', $worker_id, self::KILL_WORKER_AFTER_NO_RESPONCE_SECONDS, $worker_pid), LogLevel::CRITICAL);
    }
}