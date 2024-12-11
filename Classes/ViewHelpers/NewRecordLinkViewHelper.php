<?php

namespace Crossmedia\Fourallportal\ViewHelpers;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractTagBasedViewHelper;

/**
 * Create internal link within backend app
 * @internal
 */
class NewRecordLinkViewHelper extends AbstractTagBasedViewHelper
{

  /**
   * @var string
   */
  protected $tagName = 'a';

  /**
   * @var bool
   */
  protected $escapeOutput = false;

  /**
   * Initializes the arguments
   */
  public function initializeArguments(): void
  {
    parent::initializeArguments();
    parent::registerUniversalTagAttributes();
    $this->registerArgument('table', 'string', '', true);
  }

  /**
   * Render method
   * @return NULL|string
   * @throws RouteNotFoundException
   */
  public function render(): ?string
  {
    $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
    $uri = $uriBuilder->buildUriFromRoute('record_edit', [
      'edit' => [
        $this->arguments['table'] => [0 => 'new']
      ],
      'returnUrl' => GeneralUtility::getIndpEnv('REQUEST_URI')
    ]);
    $this->tag->addAttribute('href', $uri);
    $this->tag->setContent($this->renderChildren());
    return $this->tag->render();
  }
}
