<?php

namespace Crossmedia\Fourallportal\Response;

interface ResponseInterface
{
    /**
     * Attach the response
     *
     * @return static
     * @deprecated Use info instead or another related method
     */
    public function setDescription(string $message): static;

    /**
     * Add a error message
     *
     * @param string $message
     * @return $this
     */
    public function error(string $message): static;

    /**
     * Add a warning message
     *
     * @param string $message
     * @return $this
     */
    public function warning(string $message): static;

    /**
     * Add a info message
     *
     * @param string $message
     * @return $this
     */
    public function info(string $message): static;

    /**
     * Add a debug message
     *
     * @param string $message
     * @return $this
     */
    public function debug(string $message): static;

    /**
     * Attach the response
     *
     * @return void
     */
    public function send(): void;

    /**
     * Returns the collected response
     *
     * @return string
     */
    public function getCollected(): string;
}
