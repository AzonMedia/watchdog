<?php
declare(strict_types=1);

namespace Azonmedia\Watchdog\Interfaces;

interface BackendInterface
{
    public function checkin(int $worker_pid, int $worker_id) : void;
    
    public function check() : void;
    
    public function delete_record(string $worker_id) : void; 
}

