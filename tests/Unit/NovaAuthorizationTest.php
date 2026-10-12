<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Movecloser\ProcessManager\Models\Process;
use Movecloser\ProcessManager\Models\ProcessStep;
use Movecloser\ProcessManager\Nova\Resources\Process as ProcessResource;
use Movecloser\ProcessManager\Nova\Resources\ProcessStep as ProcessStepResource;
use Movecloser\ProcessManager\ProcessManagerFactory;
use ReflectionProperty;
use Tests\TestCase;

class NovaAuthorizationTest extends TestCase
{
    protected function tearDown(): void
    {
        (new ReflectionProperty(ProcessManagerFactory::class, 'viewResolver'))->setValue(null, null);

        parent::tearDown();
    }

    public function test_process_cannot_be_updated_through_api(): void
    {
        $this->expectException(AuthorizationException::class);

        (new ProcessResource(new Process()))->authorizeToUpdate(Request::create('/'));
    }

    public function test_process_step_cannot_be_updated_through_api(): void
    {
        $this->expectException(AuthorizationException::class);

        (new ProcessStepResource(new ProcessStep()))->authorizeToUpdate(Request::create('/'));
    }

    public function test_process_detail_requires_view_permission(): void
    {
        ProcessManagerFactory::setViewResolver(static fn() => false);

        $this->expectException(AuthorizationException::class);

        (new ProcessResource(new Process()))->authorizeToView(Request::create('/'));
    }

    public function test_process_detail_is_allowed_with_view_permission(): void
    {
        ProcessManagerFactory::setViewResolver(static fn() => true);

        (new ProcessResource(new Process()))->authorizeToView(Request::create('/'));

        $this->addToAssertionCount(1);
    }

    public function test_process_steps_index_requires_view_permission(): void
    {
        ProcessManagerFactory::setViewResolver(static fn() => false);

        $this->assertFalse(ProcessStepResource::authorizedToViewAny(Request::create('/')));
    }
}
