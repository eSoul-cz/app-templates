<?php

declare(strict_types=1);

namespace ESoul\AppTemplates;

/**
 * What an addon needs after its files are in a project that has lock files: `composer update <added packages> -W`, `pnpm install`
 * and `esoul-guidelines sync`. Only the packages the merges added are updated, never the ones the project already had.
 */
final readonly class AddonInstaller
{
    public function __construct(private CommandRunner $runner, private string $builderGuidelinesBin) {
    }

    /**
     * @return list<string> the commands that were run, for the report
     */
    public function install(string $project, AddonReport $report): array {
        $commands = [];

        $packages = $report->addedPackages();
        if ($packages !== [] && is_file($project . '/composer.lock')) {
            $commands[] = ['composer', 'update', ...$packages, '-W'];
        }
        if ($report->wrote('package.json') && is_file($project . '/pnpm-lock.yaml')) {
            $commands[] = ['pnpm', 'install'];
        }
        if (is_file($project . '/' . Builder::GUIDELINES_CONFIG)) {
            $own = $project . '/vendor/bin/esoul-guidelines';
            $commands[] = GuidelinesRenderer::syncCommand(is_file($own) ? $own : $this->builderGuidelinesBin);
        }

        $ran = [];
        foreach ($commands as $command) {
            $this->runner->run($command, $project);
            $ran[] = implode(' ', $command);
        }

        return $ran;
    }
}
