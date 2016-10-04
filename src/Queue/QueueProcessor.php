<?php

namespace Glooby\TaskBundle\Queue;

use Doctrine\Common\Persistence\ManagerRegistry;
use Glooby\TaskBundle\Entity\QueuedTask;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * @author Emil Kilhage
 */
class QueueProcessor
{
    /**
     * @var int
     */
    private $limit;

    /**
     * @var bool
     */
    private $debug;

    /**
     * @var Process[]
     */
    private $processes = [];

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * @var ManagerRegistry
     */
    protected $doctrine;

    /**
     * @param ManagerRegistry $doctrine
     */
    public function setDoctrine($doctrine)
    {
        $this->doctrine = $doctrine;
    }

    /**
     * @param OutputInterface $output
     */
    public function setOutput(OutputInterface $output)
    {
        $this->output = $output;
    }

    /**
     * @param boolean $debug
     */
    public function setDebug(bool $debug)
    {
        $this->debug = $debug;
    }

    /**
     * @param int $limit
     */
    public function setLimit(int $limit)
    {
        $this->limit = $limit;
    }

    /**
     * @throws \Exception
     */
    public function process()
    {
        $queueRepo = $this->doctrine->getManager()
            ->getRepository('GloobyTaskBundle:QueuedTask');

        $that = $this;

        foreach ($queueRepo->findPending($this->limit) as $queuedTask) {
            $command = sprintf(
                'php -d memory_limit=%s app/console task:run --id=%s %s',
                ini_get('memory_limit'),
                $queuedTask->getId(),
                $this->getProcessParams()
            );

            $nl = false;
            $process = new Process($command);
            $process->setTimeout(0);
            $process->start(function ($type, $data) use ($that, &$nl) {
                if (null !== $that->output) {
                    if ($nl) {
                        $nl = false;
                        $that->output->write("\n");
                    }

                    $that->output->write($data);
                }
            });

            $this->processes[] = $process;

            if (null !== $that->output) {
                $this->output->writeln("$command");
            }
        }

        $this->wait();
    }

    /**
     * @return string
     */
    private function getProcessParams()
    {
        $params = [];

        if (!$this->debug) {
            $params[] = '--env=prod';
        }

        return implode(' ', $params);
    }

    /**
     *
     */
    private function wait()
    {
        while (count($this->processes) > 0) {
            sleep(1);

            foreach ($this->processes as $i => $process) {
                if (!$process->isRunning()) {
                    unset($this->processes[$i]);
                    echo $process->getOutput();
                }
            }
        }
    }
}