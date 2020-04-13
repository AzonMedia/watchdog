<?php
declare(strict_types=1);

namespace Azonmedia\Watchdog\Backends;

use Azonmedia\Watchdog\Interfaces\BackendInterface;
use Azonmedia\Watchdog\Watchdog;
use Guzaba2\Kernel\Kernel;
use Psr\Log\LogLevel;

class SwooleTableBackend
    implements BackendInterface
{
    protected const CONFIG_DEFAULTS = [
        'max_rows'                      => 100000,
        'cleanup_at_percentage_usage'   => 95,//when the cleanup should be triggered
        'cleanup_percentage_records'    => 20,//the percentage of records to be removed
    ];
    
    /**
     * Contains mapping between PHP and Swoole Table types
     */
    private const TYPES_MAP = [
        'int'       => \Swoole\Table::TYPE_INT,
        'float'     => \Swoole\Table::TYPE_FLOAT,
        'string'    => \Swoole\Table::TYPE_STRING,
    ];

    /**
     * @var \Swoole\Table
     */

    public const DATA_STRUCT = [
        'updated_by_worker_id'    => 'int',
        'worker_pid'              => 'int',  
        'updated_time'            => 'int',
        //'updated_by_coroutine_id' => 'int',
    ];
    

    public $SwooleTable;
    
    public function __construct()
    {
        $this->SwooleTable = new \Swoole\Table(static::CONFIG_DEFAULTS['max_rows']);

        foreach (self::DATA_STRUCT as $key => $php_type) {
            $this->SwooleTable->column($key, self::TYPES_MAP[$php_type]);
        }
        
        $this->SwooleTable->create();
    }
    
    public function checkin(int $worker_pid, int $worker_id) : void
    {

        \Swoole\Timer::tick(Watchdog::HEART_BEAT_MILISECONDS, function() use ($worker_pid, $worker_id) : void {
                $this->SwooleTable->set((string) $worker_id , array('updated_by_worker_id' => $worker_id, 'worker_pid' => $worker_pid, 'updated_time' => time()));
                //Kernel::log('watchdog checkin', LogLevel::CRITICAL);     
        });   
    }
    
    public function check() : void
    {

        \Swoole\Timer::tick(Watchdog::CHECK_WORKER_STATUS_MILISECONDS, function () : void {
            foreach($this->SwooleTable as $row) {
                //Kernel::log('watchdog check'.$row['worker_pid'], LogLevel::CRITICAL);
                if (time() - $row['updated_time'] > Watchdog::KILL_WORKER_AFTER_NO_RESPONCE_SECONDS) {
                    Watchdog::kill_worker($row['worker_pid'], $row['updated_by_worker_id']);
                    // May be delete record is not needed, because after worker is killed a new one with same id is started.
                    // But just in case i will delete record from swoole table.
                    $this->delete_record((string) $row['updated_by_worker_id']);
                }
           }
        });
    }
    
    public function delete_record(string $worker_id) : void 
    {
        $this->SwooleTable->del($worker_id);
    }
    
    /**
     * Destroys the SwooleTable
     */
    public function __destruct()
    {
        $this->SwooleTable->destroy();
        $this->SwooleTable = NULL;
    }   
}
