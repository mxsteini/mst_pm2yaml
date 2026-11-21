<?php

declare(strict_types=1);

namespace MSTITB\MstPm2yaml\Command;

use MSTITB\MstPm2yaml\Service\FormYamlService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'pm2yaml:export', description: 'Powermail-Formular als YAML-Datei exportieren und damit verknüpfen')]
final class ExportCommand extends Command
{
    public function __construct(private readonly FormYamlService $service)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('form', InputArgument::REQUIRED, 'uid des Formulars (Standardsprache)')
            ->addOption('file', null, InputOption::VALUE_REQUIRED, 'Ziel, z. B. EXT:mstitb_bootstrap/Configuration/Powermail/kontakt.yaml (nur Development) oder fileadmin/_Powermail/kontakt.yaml. Standard: verknüpfte Datei bzw. neue Datei im Ordner der Site');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $uid = (int)$input->getArgument('form');
        $file = $input->getOption('file');
        if ($file === null && $this->service->fileValue($uid) !== '') {
            $file = $this->service->fileValue($uid);
        }
        $value = $this->service->exportTo($uid, $file);
        $output->writeln(sprintf('Formular %d → %s', $uid, $value));
        return Command::SUCCESS;
    }
}
