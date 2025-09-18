<?php
declare(strict_types=1);

namespace Crossmedia\Fourallportal\Response;

/**
 * Use this response for tests or in case you did not need the output
 */
class NullResponse implements ResponseInterface
{
    /**
     * {@inheritDoc}
     */
    public function setDescription(string $message): static
    {
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function error(string $message): static
    {
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function warning(string $message): static
    {
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function info(string $message): static
    {
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function debug(string $message): static
    {
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
