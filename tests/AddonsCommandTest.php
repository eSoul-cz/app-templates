<?php

declare(strict_types=1);

namespace ESoul\AppTemplates\Tests;

use ESoul\AppTemplates\Command\AddonsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class AddonsCommandTest extends AddonTestCase
{
    /** @param array<string, bool> $options */
    private function runCommand(array $options = []): CommandTester {
        $tester = new CommandTester(new AddonsCommand($this->root));
        $tester->execute($options);

        return $tester;
    }

    public function test_json_lists_each_addon_with_its_declared_variants(): void {
        $this->variant([['layer' => 'base']], 'other');
        $this->put('layers/addons/pulse/core/files/a.txt', 'a');
        $this->put('layers/addons/pulse/extra/files/b.txt', 'b');
        $this->manifest('pulse', ['demo' => ['addons/pulse/core'], 'other' => ['addons/pulse/core', 'addons/pulse/extra']]);
        $this->put('layers/addons/zeta/core/files/c.txt', 'c');
        $this->manifest('zeta', ['other' => ['addons/zeta/core']]);

        $tester = $this->runCommand(['--json' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame(
            [
                ['name' => 'pulse', 'description' => 'Demo addon.', 'variants' => ['demo', 'other']],
                ['name' => 'zeta', 'description' => 'Demo addon.', 'variants' => ['other']],
            ],
            json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function test_json_is_an_empty_list_without_addons(): void {
        $tester = $this->runCommand(['--json' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame([], json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR));
    }

    public function test_the_table_names_addons_and_variants(): void {
        $this->put('layers/addons/pulse/core/files/a.txt', 'a');
        $this->manifest('pulse');

        $display = $this->display($this->runCommand());

        self::assertStringContainsString('pulse', $display);
        self::assertStringContainsString('demo', $display);
        self::assertStringContainsString('Demo addon.', $display);
    }

    public function test_an_invalid_manifest_fails_the_command(): void {
        $this->manifest('pulse', ['nope' => ['addons/pulse/core']]);

        $tester = $this->runCommand(['--json' => true]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('unknown variant "nope"', $this->display($tester));
    }
}
