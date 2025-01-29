<?php
declare(strict_types=1);

namespace Crossmedia\Fourallportal\Response;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Response interface for CLIS commands
 */
class ConsoleResponse implements ResponseInterface
{
    public function __construct(private OutputInterface $output)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function setDescription(string $message): static
    {
        return $this->info($message);
    }

    /**
     * {@inheritDoc}
     */
    public function error(string $message): static
    {
        $this->output->writeln(trim($message));
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function warning(string $message): static
    {
        if ($this->output->isVerbose()) {
            $this->output->writeln(trim($message));
        }
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function info(string $message): static
    {
        if (!$this->output->isQuiet() && $this->output->isVeryVerbose()) {
            $this->output->writeln(trim($message));
        }
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function debug(string $message): static
    {
        if (!$this->output->isQuiet() && $this->output->isDebug()) {
            $this->output->writeln(trim($message));
        }
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function send(): void
    {
        // Do nothing
    }

    /**
     * {@inheritDoc}
     */
    public function getCollected(): string
    {
        // Not relevant for console commands
        return '';
    }
}
