<?php

declare(strict_types=1);

namespace MSTITB\MstPm2yaml\Command;

use MSTITB\MstPm2yaml\Service\FormYamlService;
use MSTITB\MstPm2yaml\Service\Records;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'pm2yaml:sync', description: 'Powermail-Formulare aus ihren YAML-Dateien in die Datenbank übernehmen')]
final class SyncCommand extends Command
{
    public function __construct(private readonly FormYamlService $service)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('form', 'f', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Nur diese Formular-uid(s)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Nur prüfen, nichts speichern')
            ->addOption('only-locked', null, InputOption::VALUE_NONE, 'Nur versionierte Dateien, die hier schreibgeschützt sind (Deployment)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $only = array_map('intval', (array)$input->getOption('form'));
        $dryRun = (bool)$input->getOption('dry-run');
        $errors = 0;
        foreach ($this->service->linkedForms() as $form) {
            $uid = (int)$form['uid'];
            if (($only !== [] && !in_array($uid, $only, true)) || ($input->getOption('only-locked') && !$this->service->isLocked($uid))) {
                continue;
            }
            $label = sprintf('[%d] %s ← %s', $uid, $form['title'], $form[Records::FILE_COLUMN]);
            try {
                $changed = $this->service->importFromFile($uid, $dryRun);
                $io->writeln($label . ': ' . ($changed ? ($dryRun ? 'würde geändert' : 'geändert') : 'unverändert'));
            } catch (\Throwable $e) {
                $errors++;
                $io->error($label . ': ' . $e->getMessage());
            }
        }
        return $errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
