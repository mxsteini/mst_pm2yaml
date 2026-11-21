<?php

declare(strict_types=1);

namespace MSTITB\MstPm2yaml\Tests\Unit\Service;

use MSTITB\MstPm2yaml\Service\FormExporter;
use MSTITB\MstPm2yaml\Service\FormImporter;
use MSTITB\MstPm2yaml\Service\Records;
use MSTITB\MstPm2yaml\Tests\Unit\Fixtures\ColumnsFactory;
use MSTITB\MstPm2yaml\Tests\Unit\Fixtures\InMemoryRecords;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class FormExporterTest extends UnitTestCase
{
    private InMemoryRecords $records;
    private FormExporter $exporter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->records = new InMemoryRecords();
        $this->records->add(Records::FORM, ['uid' => 1, 'title' => 'Kontakt', Records::FILE_COLUMN => 'fileadmin/_Powermail/kontakt.yaml']);
        $this->exporter = new FormExporter($this->records, ColumnsFactory::create());
    }

    #[Test]
    public function defaultsAreOmittedAndTypeAndTitleComeFirst(): void
    {
        $page = $this->records->add(Records::PAGE, ['form' => 1, Records::IDENTIFIER_COLUMN => 'start', 'title' => 'Start']);
        $this->records->add(Records::FIELD, ['page' => $page, 'marker' => 'name', 'css' => 'col', 'mandatory' => 1, 'type' => 'input', 'title' => '']);

        self::assertSame([
            'title' => 'Kontakt',
            'pages' => [
                'start' => [
                    'title' => 'Start',
                    'fields' => [
                        'name' => ['type' => 'input', 'title' => '', 'mandatory' => 1, 'css' => 'col'],
                    ],
                ],
            ],
        ], $this->exporter->build(1));
    }

    #[Test]
    public function windowsLineBreaksBecomeUnixLineBreaks(): void
    {
        $page = $this->records->add(Records::PAGE, ['form' => 1, Records::IDENTIFIER_COLUMN => 'start']);
        $this->records->add(Records::FIELD, ['page' => $page, 'marker' => 'app', 'type' => 'radio', 'settings' => "A\r\nB\rC"]);

        self::assertSame("A\nB\nC", $this->exporter->build(1)['pages']['start']['fields']['app']['settings']);
    }

    #[Test]
    public function missingIdentifiersAndMarkersAreGeneratedAndStored(): void
    {
        $first = $this->records->add(Records::PAGE, ['form' => 1, 'title' => 'Deine Angaben', 'sorting' => 1]);
        $second = $this->records->add(Records::PAGE, ['form' => 1, 'title' => 'Deine Angaben', 'sorting' => 2]);
        $third = $this->records->add(Records::PAGE, ['form' => 1, 'title' => '???', 'sorting' => 3]);
        $field = $this->records->add(Records::FIELD, ['page' => $first, 'marker' => '', 'title' => 'E-Mail Adresse', 'type' => 'input', 'sorting' => 1]);
        $duplicate = $this->records->add(Records::FIELD, ['page' => $second, 'marker' => 'e_mail_adresse', 'type' => 'input', 'sorting' => 1]);

        $data = $this->exporter->build(1);

        self::assertSame(['deine-angaben', 'deine-angaben-2', 'seite'], array_keys($data['pages']));
        self::assertSame('deine-angaben-2', $this->records->byUid(Records::PAGE, $second)[Records::IDENTIFIER_COLUMN]);
        self::assertSame('seite', $this->records->byUid(Records::PAGE, $third)[Records::IDENTIFIER_COLUMN]);
        self::assertSame('e_mail_adresse', $this->records->byUid(Records::FIELD, $field)['marker']);
        self::assertSame('e_mail_adresse_2', $this->records->byUid(Records::FIELD, $duplicate)['marker']);
    }

    #[Test]
    public function deletedHiddenWorkspaceAndTranslatedRecordsAreNotExportedAsPages(): void
    {
        $page = $this->records->add(Records::PAGE, ['form' => 1, Records::IDENTIFIER_COLUMN => 'start', 'sorting' => 1]);
        $this->records->add(Records::PAGE, ['form' => 1, Records::IDENTIFIER_COLUMN => 'geloescht', 'deleted' => 1]);
        $this->records->add(Records::PAGE, ['form' => 1, Records::IDENTIFIER_COLUMN => 'entwurf', 't3ver_wsid' => 1]);
        $this->records->add(Records::FIELD, ['page' => $page, 'marker' => 'weg', 'type' => 'input', 'deleted' => 1]);
        $this->records->add(Records::FIELD, ['page' => $page, 'marker' => 'versteckt', 'type' => 'input', 'hidden' => 1]);

        $data = $this->exporter->build(1);

        self::assertSame(['start'], array_keys($data['pages']));
        self::assertSame(['versteckt'], array_keys($data['pages']['start']['fields']));
        self::assertSame(1, $data['pages']['start']['fields']['versteckt']['hidden']);
    }

    #[Test]
    public function localizationsContainOnlyDifferences(): void
    {
        $page = $this->records->add(Records::PAGE, ['form' => 1, Records::IDENTIFIER_COLUMN => 'start', 'title' => 'Start']);
        $field = $this->records->add(Records::FIELD, ['page' => $page, 'marker' => 'name', 'type' => 'input', 'title' => 'Name']);
        $other = $this->records->add(Records::FIELD, ['page' => $page, 'marker' => 'ort', 'type' => 'input', 'title' => 'Ort', 'sorting' => 2]);
        $translatedForm = $this->records->add(Records::FORM, ['sys_language_uid' => 1, 'l10n_parent' => 1, 'title' => 'Kontakt']);
        $translatedPage = $this->records->add(Records::PAGE, ['form' => $translatedForm, 'sys_language_uid' => 1, 'l10n_parent' => $page, 'title' => 'Start']);
        $this->records->add(Records::FIELD, ['page' => $translatedPage, 'sys_language_uid' => 1, 'l10n_parent' => $field, 'marker' => 'name', 'type' => 'input', 'title' => 'Your name']);
        $this->records->add(Records::FIELD, ['page' => $translatedPage, 'sys_language_uid' => 1, 'l10n_parent' => $other, 'marker' => 'ort', 'type' => 'input', 'title' => 'Ort']);
        // Übersetzung ohne übersetztes Formular wird ignoriert
        $this->records->add(Records::FIELD, ['page' => $translatedPage, 'sys_language_uid' => 2, 'l10n_parent' => $field, 'title' => 'Nome']);

        self::assertSame([1 => ['fields' => ['name' => ['title' => 'Your name']]]], $this->exporter->build(1)['localizations']);
    }

    #[Test]
    public function exportOfImportedDataReproducesTheData(): void
    {
        $data = [
            'title' => 'Kontakt',
            'css' => 'nolabel',
            'pages' => [
                'kontakt' => [
                    'title' => 'Ihre Nachricht',
                    'fields' => [
                        'name' => ['type' => 'input', 'title' => 'Name', 'mandatory' => 1],
                        'auswahl' => ['type' => 'select', 'title' => 'Auswahl', 'settings' => "A\nB"],
                    ],
                ],
                'danke' => ['title' => 'Abschluss', 'fields' => ['absenden' => ['type' => 'submit', 'title' => 'Senden']]],
            ],
            'localizations' => [
                1 => ['title' => 'Contact', 'pages' => ['kontakt' => ['title' => 'Your message']], 'fields' => ['auswahl' => ['settings' => "A (en)\nB (en)"]]],
            ],
        ];
        (new FormImporter($this->records, ColumnsFactory::create()))->import(1, $data);

        self::assertSame($data, $this->exporter->build(1));
    }

    #[Test]
    public function unknownFormIsRejected(): void
    {
        $this->expectExceptionMessage('Formular 99 nicht gefunden');
        $this->exporter->build(99);
    }
}
