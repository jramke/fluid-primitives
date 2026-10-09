<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Unit;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Jramke\FluidPrimitives\Service\RegistryService;
use Jramke\FluidPrimitives\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class RegistryServiceTest extends TestCase
{
    #[Test]
    public function reportsWhatIsWrongWithTheAnswerOfTheRegistryInsteadOfThrowing(): void
    {
        $notFound = new ClientException('Not Found', new Request('GET', '/'), new Response(404));
        $client = $this->createStub(ClientInterface::class);
        $client->method('request')->willReturnOnConsecutiveCalls(
            new Response(200, body: '{"name":"accordion","files":["Root.html"]}'),
            new Response(200, body: '{"name":"accordion","files":[]}'),
            $this->throwException($notFound),
        );

        $registry = new RegistryService($client);

        [$error, $manifest] = $registry->fetchComponent('accordion');
        $this->assertNull($error);
        $this->assertSame(['Root.html'], $manifest['files']);

        // a manifest without files is no component, however the registry got there
        [$error] = $registry->fetchComponent('accordion');
        $this->assertSame('Invalid component manifest received from registry.', $error['message'] ?? null);

        [$error, $content] = $registry->fetchComponentFile('accordion', 'Root.html');
        $this->assertSame('Failed to fetch component file from registry.', $error['message'] ?? null);
        $this->assertNull($content);
    }
}
