<?php

namespace Crossmedia\Fourallportal\Domain\Model;

use TYPO3\CMS\Core\Log\LogLevel;

class LogEntry
{
  protected string $date = '';
  protected string $severity = LogLevel::INFO; // INFO
  protected string $message = '';

  public function __construct(string $date, string $severity, string $message)
  {
    $this->date = $date;
    $this->severity = $severity;
    $this->message = $message;
  }

  public function getDate(): string
  {
    return $this->date;
  }

  public function getSeverity(): string
  {
    return $this->severity;
  }

  public function getMessage(): string
  {
    return $this->message;
  }

  public function getSeverityClassName(): string
  {
    return match ($this->severity) {
      LogLevel::CRITICAL, LogLevel::ERROR, LogLevel::WARNING => 'danger',
      default => 'default',
    };
  }
}
