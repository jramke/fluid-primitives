<?php

declare(strict_types=1);

use Jramke\FluidPrimitives\Registry\HydrationRegistry;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\CMS\Core\View\ViewFactoryData;

describe('Accordion Component Rendering', function () {
    beforeEach(function () {
        // Set up a frontend request for proper context
        $request = new ServerRequest();
        $request = $request
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE)
            ->withAttribute('normalizedParams', NormalizedParams::createFromRequest($request));
        $GLOBALS['TYPO3_REQUEST'] = $request;

        // Get the ViewFactory from the container
        $viewFactory = $this->get(ViewFactoryInterface::class);

        // Get the template paths from the extension
        $extensionPath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('fluid_primitives');

        // Create a view using the factory
        $this->view = $viewFactory->create(new ViewFactoryData(
            templateRootPaths: [$extensionPath . 'Resources/Private/Primitives'],
        ));

        // Register the primitives namespace
        $this->view->getRenderingContext()->getViewHelperResolver()->addNamespace(
            'primitives',
            new \Jramke\FluidPrimitives\Component\ComponentPrimitivesCollection()
        );

        // Register the ui namespace
        $this->view->getRenderingContext()->getViewHelperResolver()->addNamespace(
            'ui',
            'Jramke\\FluidPrimitives\\ViewHelpers'
        );

        // Clear the hydration registry before each test
        HydrationRegistry::getInstance()->clear();
    });

    afterEach(function () {
        // Clean up the global request
        unset($GLOBALS['TYPO3_REQUEST']);
    });

    describe('basic structure', function () {
        it('renders accordion root with data attributes', function () {
            $this->view->getRenderingContext()->getTemplatePaths()->setTemplateSource('
                <primitives:accordion.root>
                    <primitives:accordion.item value="item-1">
                        <primitives:accordion.itemTrigger>Trigger 1</primitives:accordion.itemTrigger>
                        <primitives:accordion.itemContent>Content 1</primitives:accordion.itemContent>
                    </primitives:accordion.item>
                </primitives:accordion.root>
            ');

            $html = $this->view->render();

            // Should contain data-scope="accordion" for the root
            expect($html)->toContain('data-scope="accordion"');
            expect($html)->toContain('data-part="root"');
        });

        it('generates unique rootId for hydration', function () {
            $this->view->getRenderingContext()->getTemplatePaths()->setTemplateSource('
                <primitives:accordion.root>
                    <primitives:accordion.item value="item-1">
                        <primitives:accordion.itemTrigger>Trigger</primitives:accordion.itemTrigger>
                        <primitives:accordion.itemContent>Content</primitives:accordion.itemContent>
                    </primitives:accordion.item>
                </primitives:accordion.root>
            ');

            $html = $this->view->render();

            // Should contain data-hydrate-accordion with a unique ID
            expect($html)->toMatch('/data-hydrate-accordion="[^"]+"/');
        });

        it('renders accordion items with value attribute', function () {
            $this->view->getRenderingContext()->getTemplatePaths()->setTemplateSource('
                <primitives:accordion.root>
                    <primitives:accordion.item value="my-unique-value">
                        <primitives:accordion.itemTrigger>Trigger</primitives:accordion.itemTrigger>
                        <primitives:accordion.itemContent>Content</primitives:accordion.itemContent>
                    </primitives:accordion.item>
                </primitives:accordion.root>
            ');

            $html = $this->view->render();

            // Item should have data-value attribute
            expect($html)->toContain('data-part="item"');
            expect($html)->toContain('data-value="my-unique-value"');
        });
    });

    describe('item trigger', function () {
        it('renders as button element', function () {
            $this->view->getRenderingContext()->getTemplatePaths()->setTemplateSource('
                <primitives:accordion.root>
                    <primitives:accordion.item value="item-1">
                        <primitives:accordion.itemTrigger>Click me</primitives:accordion.itemTrigger>
                        <primitives:accordion.itemContent>Content</primitives:accordion.itemContent>
                    </primitives:accordion.item>
                </primitives:accordion.root>
            ');

            $html = $this->view->render();

            expect($html)->toContain('<button');
            expect($html)->toContain('type="button"');
            expect($html)->toContain('data-part="item-trigger"');
        });

        it('has aria-disabled attribute', function () {
            $this->view->getRenderingContext()->getTemplatePaths()->setTemplateSource('
                <primitives:accordion.root>
                    <primitives:accordion.item value="item-1">
                        <primitives:accordion.itemTrigger>Trigger</primitives:accordion.itemTrigger>
                        <primitives:accordion.itemContent>Content</primitives:accordion.itemContent>
                    </primitives:accordion.item>
                </primitives:accordion.root>
            ');

            $html = $this->view->render();

            expect($html)->toContain('aria-disabled="false"');
        });

        it('renders disabled state correctly', function () {
            $this->view->getRenderingContext()->getTemplatePaths()->setTemplateSource('
                <primitives:accordion.root>
                    <primitives:accordion.item value="item-1" disabled="{true}">
                        <primitives:accordion.itemTrigger>Trigger</primitives:accordion.itemTrigger>
                        <primitives:accordion.itemContent>Content</primitives:accordion.itemContent>
                    </primitives:accordion.item>
                </primitives:accordion.root>
            ');

            $html = $this->view->render();

            expect($html)->toContain('aria-disabled="true"');
            expect($html)->toContain('disabled');
            expect($html)->toContain('data-disabled');
        });
    });

    describe('item content', function () {
        it('renders content container with data attributes', function () {
            $this->view->getRenderingContext()->getTemplatePaths()->setTemplateSource('
                <primitives:accordion.root>
                    <primitives:accordion.item value="item-1">
                        <primitives:accordion.itemTrigger>Trigger</primitives:accordion.itemTrigger>
                        <primitives:accordion.itemContent>My Content Here</primitives:accordion.itemContent>
                    </primitives:accordion.item>
                </primitives:accordion.root>
            ');

            $html = $this->view->render();

            expect($html)->toContain('data-part="item-content"');
            expect($html)->toContain('My Content Here');
        });
    });

    describe('multiple items', function () {
        it('renders multiple accordion items', function () {
            $this->view->getRenderingContext()->getTemplatePaths()->setTemplateSource('
                <primitives:accordion.root>
                    <primitives:accordion.item value="first">
                        <primitives:accordion.itemTrigger>First Trigger</primitives:accordion.itemTrigger>
                        <primitives:accordion.itemContent>First Content</primitives:accordion.itemContent>
                    </primitives:accordion.item>
                    <primitives:accordion.item value="second">
                        <primitives:accordion.itemTrigger>Second Trigger</primitives:accordion.itemTrigger>
                        <primitives:accordion.itemContent>Second Content</primitives:accordion.itemContent>
                    </primitives:accordion.item>
                </primitives:accordion.root>
            ');

            $html = $this->view->render();

            expect($html)->toContain('data-value="first"');
            expect($html)->toContain('data-value="second"');
            expect($html)->toContain('First Trigger');
            expect($html)->toContain('Second Trigger');
        });
    });

    describe('props', function () {
        it('applies custom class to root', function () {
            $this->view->getRenderingContext()->getTemplatePaths()->setTemplateSource('
                <primitives:accordion.root class="my-custom-class">
                    <primitives:accordion.item value="item-1">
                        <primitives:accordion.itemTrigger>Trigger</primitives:accordion.itemTrigger>
                        <primitives:accordion.itemContent>Content</primitives:accordion.itemContent>
                    </primitives:accordion.item>
                </primitives:accordion.root>
            ');

            $html = $this->view->render();

            expect($html)->toContain('class="my-custom-class"');
        });

        it('passes orientation prop through data attribute', function () {
            $this->view->getRenderingContext()->getTemplatePaths()->setTemplateSource('
                <primitives:accordion.root orientation="horizontal">
                    <primitives:accordion.item value="item-1">
                        <primitives:accordion.itemTrigger>Trigger</primitives:accordion.itemTrigger>
                        <primitives:accordion.itemContent>Content</primitives:accordion.itemContent>
                    </primitives:accordion.item>
                </primitives:accordion.root>
            ');

            $html = $this->view->render();

            expect($html)->toContain('data-orientation="horizontal"');
        });
    });

    describe('hydration data', function () {
        it('registers component in hydration registry', function () {
            $this->view->getRenderingContext()->getTemplatePaths()->setTemplateSource('
                <primitives:accordion.root>
                    <primitives:accordion.item value="item-1">
                        <primitives:accordion.itemTrigger>Trigger</primitives:accordion.itemTrigger>
                        <primitives:accordion.itemContent>Content</primitives:accordion.itemContent>
                    </primitives:accordion.item>
                </primitives:accordion.root>
            ');

            $this->view->render();

            $hydrationData = HydrationRegistry::getInstance()->getAll();

            expect($hydrationData)->toHaveKey('accordion');
            expect($hydrationData['accordion'])->toBeArray();
            expect(count($hydrationData['accordion']))->toBe(1);
        });

        it('includes client props in hydration data', function () {
            $this->view->getRenderingContext()->getTemplatePaths()->setTemplateSource('
                <primitives:accordion.root multiple="{true}" collapsible="{true}">
                    <primitives:accordion.item value="item-1">
                        <primitives:accordion.itemTrigger>Trigger</primitives:accordion.itemTrigger>
                        <primitives:accordion.itemContent>Content</primitives:accordion.itemContent>
                    </primitives:accordion.item>
                </primitives:accordion.root>
            ');

            $this->view->render();

            $hydrationData = HydrationRegistry::getInstance()->getAll();
            $accordionData = array_values($hydrationData['accordion'])[0];

            expect($accordionData['props'])->toHaveKey('multiple');
            expect($accordionData['props']['multiple'])->toBe(true);
            expect($accordionData['props'])->toHaveKey('collapsible');
            expect($accordionData['props']['collapsible'])->toBe(true);
        });

        it('includes defaultValue in hydration data', function () {
            $this->view->getRenderingContext()->getTemplatePaths()->setTemplateSource('
                <primitives:accordion.root defaultValue="{0: \'item-1\'}">
                    <primitives:accordion.item value="item-1">
                        <primitives:accordion.itemTrigger>Trigger</primitives:accordion.itemTrigger>
                        <primitives:accordion.itemContent>Content</primitives:accordion.itemContent>
                    </primitives:accordion.item>
                </primitives:accordion.root>
            ');

            $this->view->render();

            $hydrationData = HydrationRegistry::getInstance()->getAll();
            $accordionData = array_values($hydrationData['accordion'])[0];

            expect($accordionData['props'])->toHaveKey('defaultValue');
            expect($accordionData['props']['defaultValue'])->toBe(['item-1']);
        });
    });

    describe('state attributes', function () {
        it('renders closed state by default', function () {
            $this->view->getRenderingContext()->getTemplatePaths()->setTemplateSource('
                <primitives:accordion.root>
                    <primitives:accordion.item value="item-1">
                        <primitives:accordion.itemTrigger>Trigger</primitives:accordion.itemTrigger>
                        <primitives:accordion.itemContent>Content</primitives:accordion.itemContent>
                    </primitives:accordion.item>
                </primitives:accordion.root>
            ');

            $html = $this->view->render();

            expect($html)->toContain('data-state="closed"');
        });

        it('renders open state when in defaultValue', function () {
            $this->view->getRenderingContext()->getTemplatePaths()->setTemplateSource('
                <primitives:accordion.root defaultValue="{0: \'item-1\'}">
                    <primitives:accordion.item value="item-1">
                        <primitives:accordion.itemTrigger>Trigger</primitives:accordion.itemTrigger>
                        <primitives:accordion.itemContent>Content</primitives:accordion.itemContent>
                    </primitives:accordion.item>
                </primitives:accordion.root>
            ');

            $html = $this->view->render();

            expect($html)->toContain('data-state="open"');
        });
    });
});
