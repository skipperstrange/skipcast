<?php

namespace App\Services;

use phpseclib3\Net\SSH2;
use phpseclib3\Crypt\PublicKeyLoader;
use App\Models\Channel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class LiquidsoapCommandService
{
    protected $sshConfig;
    protected $isRemote;
    
    public function __construct()
    {
        $this->sshConfig = [
            'host' => config('liquidsoap.host'),
            'user' => config('liquidsoap.ssh_user', 'liquidsoap'),
            'key_path' => config('liquidsoap.ssh_key_path'),
        ];
        
        // Determine if we're running on the same server as Liquidsoap
        $this->isRemote = config('liquidsoap.host') !== 'localhost' && 
                         config('liquidsoap.host') !== '127.0.0.1';
    }
    
    /**
     * Start a Liquidsoap stream for a channel
     */
    public function startStream(Channel $channel): bool
    {
        try {
            return $this->isRemote 
                ? $this->executeRemoteCommand('start', $channel)
                : $this->executeLocalCommand('start', $channel);
        } catch (\Exception $e) {
            Log::error("Failed to start Liquidsoap stream for channel {$channel->slug}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
    
    /**
     * Stop a Liquidsoap stream for a channel
     */
    public function stopStream(Channel $channel): bool
    {
        try {
            return $this->isRemote 
                ? $this->executeRemoteCommand('stop', $channel)
                : $this->executeLocalCommand('stop', $channel);
        } catch (\Exception $e) {
            Log::error("Failed to stop Liquidsoap stream for channel {$channel->slug}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
    
    /**
     * Execute a Liquidsoap command locally
     */
    protected function executeLocalCommand(string $action, Channel $channel): bool
    {
        $configPath = storage_path("app/liquidsoap/{$channel->privacy}/{$channel->slug}.liq");
        $pidFile = storage_path("app/liquidsoap/pids/{$channel->slug}.pid");
        
        if ($action === 'start') {
            // Create directory for PID files if it doesn't exist
            if (!file_exists(dirname($pidFile))) {
                mkdir(dirname($pidFile), 0755, true);
            }
            
            // Start Liquidsoap process and save PID
            $command = "liquidsoap " . escapeshellarg($configPath) . " > /dev/null 2>&1 & echo $!";
            $pid = exec($command);
            
            if (!$pid) {
                throw new \Exception("Failed to start Liquidsoap process");
            }
            
            file_put_contents($pidFile, $pid);
            
            Log::info("Started Liquidsoap stream for channel {$channel->slug} with PID {$pid}");
            return true;
        } 
        elseif ($action === 'stop') {
            if (file_exists($pidFile)) {
                $pid = file_get_contents($pidFile);
                
                if (posix_kill($pid, 0)) {
                    posix_kill($pid, SIGTERM);
                    unlink($pidFile);
                    
                    Log::info("Stopped Liquidsoap stream for channel {$channel->slug} with PID {$pid}");
                    return true;
                }
            }
            
            Log::warning("No active Liquidsoap process found for channel {$channel->slug}");
            return false;
        }
        
        throw new \Exception("Invalid action: {$action}");
    }
    
    /**
     * Execute a Liquidsoap command on a remote server via SSH
     */
    protected function executeRemoteCommand(string $action, Channel $channel): bool
    {
        try {
            $ssh = new SSH2($this->sshConfig['host']);
            
            // Load the private key
            $key = PublicKeyLoader::load(file_get_contents($this->sshConfig['key_path']));
            
            if (!$ssh->login($this->sshConfig['user'], $key)) {
                throw new \Exception("SSH authentication failed");
            }
            
            $configPath = "/var/www/liquidsoap/{$channel->privacy}/{$channel->slug}.liq";
            $pidFile = "/var/www/liquidsoap/pids/{$channel->slug}.pid";
            
            if ($action === 'start') {
                // Ensure PID directory exists
                $ssh->exec("mkdir -p " . dirname($pidFile));
                
                // Start Liquidsoap process
                $command = "liquidsoap " . escapeshellarg($configPath) . " > /dev/null 2>&1 & echo $!";
                $pid = trim($ssh->exec($command));
                
                if (!$pid) {
                    throw new \Exception("Failed to start remote Liquidsoap process");
                }
                
                $ssh->exec("echo {$pid} > {$pidFile}");
                
                Log::info("Started remote Liquidsoap stream for channel {$channel->slug} with PID {$pid}");
                return true;
            } 
            elseif ($action === 'stop') {
                // Check if PID file exists
                $result = $ssh->exec("if [ -f {$pidFile} ]; then echo 'exists'; else echo 'not_exists'; fi");
                
                if (trim($result) === 'exists') {
                    $pid = trim($ssh->exec("cat {$pidFile}"));
                    $ssh->exec("kill {$pid}");
                    $ssh->exec("rm {$pidFile}");
                    
                    Log::info("Stopped remote Liquidsoap stream for channel {$channel->slug} with PID {$pid}");
                    return true;
                }
                
                Log::warning("No PID file found for channel {$channel->slug} on remote server");
                return false;
            }
            
            throw new \Exception("Invalid action: {$action}");
        } catch (\Exception $e) {
            Log::error("SSH command execution failed: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Check if a stream is currently running
     */
    public function isStreamRunning(Channel $channel): bool
    {
        try {
            if ($this->isRemote) {
                $ssh = new SSH2($this->sshConfig['host']);
                $key = PublicKeyLoader::load(file_get_contents($this->sshConfig['key_path']));
                
                if (!$ssh->login($this->sshConfig['user'], $key)) {
                    throw new \Exception("SSH authentication failed");
                }
                
                $pidFile = "/var/www/liquidsoap/pids/{$channel->slug}.pid";
                $result = $ssh->exec("if [ -f {$pidFile} ]; then cat {$pidFile}; else echo ''; fi");
                $pid = trim($result);
                
                if ($pid) {
                    $processExists = trim($ssh->exec("ps -p {$pid} > /dev/null 2>&1 && echo 'running' || echo 'not_running'"));
                    return $processExists === 'running';
                }
            } else {
                $pidFile = storage_path("app/liquidsoap/pids/{$channel->slug}.pid");
                
                if (file_exists($pidFile)) {
                    $pid = file_get_contents($pidFile);
                    return posix_kill($pid, 0);
                }
            }
            
            return false;
        } catch (\Exception $e) {
            Log::error("Failed to check stream status: " . $e->getMessage());
            return false;
        }
    }
}
