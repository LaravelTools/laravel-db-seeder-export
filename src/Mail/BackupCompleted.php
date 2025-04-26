<?php

namespace LaravelToolkit\DbSeederExport\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BackupCompleted extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * The backup name.
     *
     * @var string
     */
    public $backupName;

    /**
     * The backup results.
     *
     * @var array
     */
    public $results;

    /**
     * Create a new message instance.
     *
     * @param string $backupName
     * @param array $results
     * @return void
     */
    public function __construct($backupName, $results)
    {
        $this->backupName = $backupName;
        $this->results = $results;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $subject = $this->results['success'] 
            ? "Database Backup Completed: {$this->backupName}" 
            : "Database Backup Failed: {$this->backupName}";
            
        return $this->subject($subject)
                    ->view('db-seeder-export::mails.backups.completed')
                    ->with([
                        'backupName' => $this->backupName,
                        'results' => $this->results,
                        'timestamp' => now()->format('Y-m-d H:i:s'),
                        'environment' => app()->environment(),
                        'databaseName' => config('database.connections.mysql.database')
                    ]);
    }
}