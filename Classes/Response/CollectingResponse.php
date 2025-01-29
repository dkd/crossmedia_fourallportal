<?php
declare(strict_types=1);

namespace Crossmedia\Fourallportal\Response;

use Symfony\Component\Console\Command\Command;
use TYPO3\CMS\Core\Log\LogLevel;

/**
 * @see .build/vendor/typo3/cms-core/Documentation/Changelog/10.0/Breaking-87193-DeprecatedFunctionalityRemoved.rst
 */
class CollectingResponse extends Command implements ResponseInterface
{
  /**
   * @var string
   */
    protected string $collected = '';

    public function error(string $message): static
    {
        return $this->setDescription($message);
    }

    /**
     * {@inheritDoc}
     */
    public function warning(string $message): static
    {
        if (isset($GLOBALS['TYPO3_CONF_VARS']['LOG']['writerConfiguration'][LogLevel::WARNING])) {
            $this->collected .= trim($message) . PHP_EOL;
        }
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function info(string $message): static
    {
        if (isset($GLOBALS['TYPO3_CONF_VARS']['LOG']['writerConfiguration'][LogLevel::INFO])) {
            $this->collected .= trim($message) . PHP_EOL;
        }
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function debug(string $message): static
    {
        if (isset($GLOBALS['TYPO3_CONF_VARS']['LOG']['writerConfiguration'][LogLevel::DEBUG])) {
            $this->collected .= trim($message) . PHP_EOL;
        }
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function send(): void
    {
        $this->collected .= $this->getDescription();
    }

  /**
   * @return string
   */
  public function getCollected(): string
  {
    return $this->collected;
  }
}
