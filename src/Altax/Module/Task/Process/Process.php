<?php
namespace Altax\Module\Task\Process;

use Symfony\Component\Process\Process as SymfonyProcess;
use Symfony\Component\Filesystem\Filesystem;
use Altax\Module\Server\Facade\Server;
use Altax\Module\Server\Resource\Node;
use Altax\Module\Task\Process\ProcessResult;
use Altax\Util\Arr;

class Process
{
    protected $node;
    protected $runtimeTask;

    public function __construct($runtimeTask, $node)
    {
        $this->runtimeTask = $runtimeTask;
        $this->node = $node;
    }

    public function run($commandline, $options = array())
    {
        if (!$this->node) {
            throw new \RuntimeException("Node is not defined to run the command.");
        }

        if (is_array($commandline)) {
            $commandline = implode(" && ", $commandline);
        }

        $this->runtimeTask->getOutput()->writeln($this->getRemoteInfoPrefix()."<info>Run: </info>$commandline");

        $realCommand = $this->compileRealCommand($commandline, $options);

        if ($this->runtimeTask->getOutput()->isVeryVerbose()) {
            $this->runtimeTask->getOutput()->writeln($this->getRemoteInfoPrefix()."<info>Real command: </info>$realCommand");
        }

        $ssh = $this->getSSH();

        $self = $this;
        if (isset($options["timeout"])) {
            $ssh->setTimeout($options["timeout"]);
        } else {
            $ssh->setTimeout(null);
        }

        $resultContent = null;
        $ssh->exec($realCommand, function ($buffer) use ($self, &$resultContent) {
            $self->getRuntimeTask()->getOutput()->write($buffer);
            $resultContent .= $buffer;
        });

        $returnCode = $ssh->getExitStatus();
        return new ProcessResult($returnCode, $resultContent);
    }

    public function runLocally($commandline, $options = array())
    {
        if (is_array($commandline)) {
            $commandline = implode(" && ", $commandline);
        }

        $this->runtimeTask->getOutput()->writeln($this->getLocalInfoPrefix()."<info>Run: </info>$commandline");

        $realCommand = $this->compileRealCommand($commandline, $options);

        if ($this->runtimeTask->getOutput()->isVeryVerbose()) {
            $this->runtimeTask->getOutput()->writeln($this->getLocalInfoPrefix()."<info>Real command: </info>$realCommand");
        }

        $self = $this;
        $symfonyProcess = new SymfonyProcess($realCommand);
        if (isset($options["timeout"])) {
            $symfonyProcess->setTimeout($options["timeout"]);
        } else {
            $symfonyProcess->setTimeout(null);
        }

        $resultContent = null;
        $returnCode = $symfonyProcess->run(function ($type, $buffer) use ($self, &$resultContent) {
            $self->getRuntimeTask()->getOutput()->write($buffer);
            $resultContent .= $buffer;
        });
        return new ProcessResult($returnCode, $resultContent);
    }

    protected function compileRealCommand($commandline, $options)
    {

        $realCommand = "";
        
        if (isset($options["user"])) {
            $realCommand .= 'sudo -u'.$options["user"].' TERM=dumb ';
        }
        
        $realCommand .= '/bin/bash -l -c "';

        if (isset($options["cwd"])) {
            $realCommand .= 'cd '.$options["cwd"].' && ';
        }

        $realCommand .= $commandline;
        $realCommand .= '"';

        return $realCommand;
    }

    protected function getSSH()
    {
        $ssh = new \Net_SSH2(
            $this->node->getHostOrDefault(),
            $this->node->getPortOrDefault());
        $key = new \Crypt_RSA();
        $key->loadKey(file_get_contents($this->node->getKeyOrDefault()));
        if (!$ssh->login($this->node->getUsernameOrDefault(), $key)) {
            throw new \RuntimeException('Unable to login '.$this->node->getName());
        }

        return $ssh;
    }


    public function get($remote, $local)
    {
        if (!$this->node) {
            throw new \RuntimeException("Node is not defined to get a file.");
        }

        $this->runtimeTask->getOutput()->writeln($this->getRemoteInfoPrefix()."<info>Get: </info>$remote -> $local");

        $sftp = $this->getSFTP();

        if (!is_dir(dirname($local))) {
            $fs = new Filesystem();
            $fs->mkdir(dirname($local));
            if ($this->runtimeTask->getOutput()->isVerbose()) {
                $this->runtimeTask->getOutput()->writeln($this->getRemoteInfoPrefix()."<info>Create directory: </info>".dirname($local));
            }
        }

        $ret = $sftp->get($remote, $local);
        if ($ret === false) {
            $this->runtimeTask->getOutput()->writeln($this->getRemoteInfoPrefix()."<error>Couldn't get: $remote -> $local</error>");
        }

        return $ret;
    }

    public function getString($remote)
    {
        if (!$this->node) {
            throw new \RuntimeException("Node is not defined to get a file.");
        }

        $this->runtimeTask->getOutput()->writeln($this->getRemoteInfoPrefix()."<info>Get: </info>$remote");

        $sftp = $this->getSFTP();
        $ret = $sftp->get($remote);
        if ($ret === false) {
            throw new \RuntimeException("Couldn't get: $remote -> $local");
        }

        return $ret;
    }

    public function put($local, $remote)
    {
        if (!$this->node) {
            throw new \RuntimeException("Node is not defined to put a file.");
        }

        $this->runtimeTask->getOutput()->writeln($this->getRemoteInfoPrefix()."<info>Put: </info>$local -> $remote");

        $sftp = $this->getSFTP();
        
        if (!is_file($local)) {
           throw new \RuntimeException("Couldn't put: $local -> $remote");
        }

        $sftp->put($remote, $local, NET_SFTP_LOCAL_FILE);
    }

    public function putString($remote, $contents)
    {
        if (!$this->node) {
            throw new \RuntimeException("Node is not defined to put a file.");
        }

        $this->runtimeTask->getOutput()->writeln($this->getRemoteInfoPrefix()."<info>Put: </info>$remote");

        $sftp = $this->getSFTP();
        $sftp->put($remote, $contents);
    }

    protected function getSFTP()
    {
        $sftp = new \Net_SFTP(
            $this->node->getHostOrDefault(), 
            $this->node->getPortOrDefault());
        $key = new \Crypt_RSA();
        $key->loadKey(file_get_contents($this->node->getKeyOrDefault()));
        if (!$sftp->login($this->node->getUsernameOrDefault(), $key)) {
            throw new \RuntimeException('Unable to login '.$this->node->getName());
        }

        return $sftp;
    }

    public function getNodeName()
    {
        $name = null;
        if (!$this->node) {
            $name = "localhost";
        } else {
            $name = $this->node->getName();
        }

        return $name;
    }

    public function getNode()
    {
        return $this->node;
    }
    public function getRemoteInfoPrefix()
    {
        return "<info>[</info><comment>".$this->getNodeName().":".posix_getpid()."</comment><info>]</info> ";
    }

    public function getLocalInfoPrefix()
    {
        return "<info>[</info><comment>localhost:".posix_getpid()."</comment><info>]</info> ";
    }

    public function getRuntimeTask()
    {
        return $this->runtimeTask;
    }
}