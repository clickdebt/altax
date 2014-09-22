<?php
namespace Altax\Shell;

use Symfony\Component\Process\Process as SymfonyProcess;

class Command
{
    protected $commandline;
    protected $process;
    protected $node;
    protected $output;
    protected $options = array();

    public function __construct($commandline, $process, $output)
    {
        $this->commandline = $commandline;
        $this->process = $process;
        $this->node = $process->getNode();
        $this->output = $output;
    }

    public function run()
    {
        if (!$this->node) {
            throw new \RuntimeException("Node is not defined to run the command.");
        }

        $commandline = $this->commandline;

        if (is_array($commandline)) {
            $commandline = implode(" && ", $commandline);
        }

        $realCommand = $this->compileRealCommand($commandline);

        $ssh = $this->node->getSSHConnection();
        if (isset($this->options["timeout"])) {
            $ssh->setTimeout($this->options["timeout"]);
        } else {
            $ssh->setTimeout(null);
        }

        if ($this->output->isDebug()) {
            $this->output->writeln(
                $this->process->getNodeInfo().
                "<info>Run command: </info>$commandline (actually: <comment>$realCommand</comment>)");
        } else {
            $this->output->writeln(
                $this->process->getNodeInfo().
                "<info>Run command: </info>$commandline");
        }

        $output = $this->output;
        $resultContent = null;
        $ssh->exec($realCommand, function ($buffer) use ($output, &$resultContent) {
            $output->write($buffer);
            $resultContent .= $buffer;
        });

        $returnCode = $ssh->getExitStatus();

        return new CommandResult($returnCode, $resultContent);

    }

    public function runLocally()
    {
        $commandline = $this->commandline;

        if (is_array($commandline)) {
            $commandline = implode(" && ", $commandline);
        }

        $realCommand = $this->compileRealCommand($commandline);

        $symfonyProcess = new SymfonyProcess($realCommand);
        if (isset($this->options["timeout"])) {
            $symfonyProcess->setTimeout($this->options["timeout"]);
        } else {
            $symfonyProcess->setTimeout(null);
        }

        if ($this->output->isDebug()) {
            $this->output->writeln(
                "<info>Run command: </info>$commandline (actually: <comment>$realCommand</comment>)");
        } else {
            $this->output->writeln(
                "<info>Run command: </info>$commandline");
        }

        $output = $this->output;
        $resultContent = null;
        $returnCode = $symfonyProcess->run(function ($type, $buffer) use ($output, &$resultContent) {
            $output->write($buffer);
            $resultContent .= $buffer;
        });

        return new CommandResult($returnCode, $resultContent);
    }

    public function cwd($value)
    {
        return $this->setOption("cwd", $value);

    }

    public function user($value)
    {
        return $this->setOption("user", $value);
    }

    public function timeout($value)
    {
        return $this->setOption("timeout", $value);
    }

    public function setOption($key, $value)
    {
        $this->options[$key] = $value;

        return $this;
    }

    protected function compileRealCommand($commandline)
    {
        $realCommand = "";

        if (isset($this->options["user"])) {
            $realCommand .= 'sudo -u'.$this->options["user"].' TERM=dumb ';
        }

        $realCommand .= '/bin/bash -l -c "';

        if (isset($this->options["cwd"])) {
            $realCommand .= 'cd '.$this->options["cwd"].' && ';
        }

        $realCommand .= str_replace('"', '\"', $commandline);
        $realCommand .= '"';

        return $realCommand;
    }
}