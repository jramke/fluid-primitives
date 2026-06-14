<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests;

use TYPO3Fluid\Fluid\View\TemplateView;

/**
 * Base test case for ViewHelper integration tests.
 *
 * Uses standalone Fluid (typo3fluid/fluid) for testing ViewHelpers
 * without requiring full TYPO3 bootstrapping.
 */
abstract class ViewHelperTestCase extends TestCase
{
    protected TemplateView $view;

    /** @var array<string, mixed> */
    protected array $variables = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->view = new TemplateView();
        $this->variables = [];

        // Register the fluid-primitives namespace
        $this->view
            ->getRenderingContext()
            ->getViewHelperResolver()
            ->addNamespace('ui', 'Jramke\\FluidPrimitives\\ViewHelpers');
    }

    /**
     * Assign a variable to the template.
     */
    protected function assign(string $key, mixed $value): self
    {
        $this->variables[$key] = $value;
        return $this;
    }

    /**
     * Render a Fluid template string.
     */
    protected function renderTemplate(string $template): string
    {
        $this->view->getRenderingContext()->getTemplatePaths()->setTemplateSource($template);

        $this->view->assignMultiple($this->variables);

        return $this->view->render();
    }

    /**
     * Normalize whitespace in HTML for comparison.
     */
    protected function normalizeHtml(string $html): string
    {
        // Remove leading/trailing whitespace
        $html = trim($html);
        // Normalize multiple whitespace to single space
        $html = preg_replace('/\s+/', ' ', $html);
        // Remove whitespace between tags
        return preg_replace('/>\s+</', '><', $html);
    }
}
