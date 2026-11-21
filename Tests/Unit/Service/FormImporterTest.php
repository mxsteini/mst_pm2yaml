<?php

declare(strict_types=1);

namespace MSTITB\MstPm2yaml\Tests\Unit\Service;

use MSTITB\MstPm2yaml\Service\FormImporter;
use MSTITB\MstPm2yaml\Service\Records;
use MSTITB\MstPm2yaml\Tests\Unit\Fixtures\ColumnsFactory;
use MSTITB\MstPm2yaml\Tests\Unit\Fixtures\InMemoryRecords;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class FormImporterTest extends UnitTestCase
{
    private InMemoryRecords $records;
    private FormImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->records = new InMemoryRecords();
        $this->records->add(Records::FORM, ['uid' => 1, 'title' => 'Alt']);
        $this->importer = new FormImporter($this->records, ColumnsFactory::create());
    }

    private static function contactForm(): array
    {
        return [
            'title' => 'Kontakt',
            'pages' => [
                'kontakt' => [
                    'title' => 'Ihre Nachricht',
                    'fields' => [
                        'name' => ['type' => 'input', 'title' => 'Name', 'mandatory' => true],
                        'email' => ['type' => 'input', 'title' => 'E-Mail'],
                    ],
                ],
                'danke' => [
                    'title' => 'Abschluss',
                    'fields' => [
                        'absenden' => ['type' => 'submit', 'title' => 'Senden'],
                    ],
                ],
            ],
        ];
    }

    private function page(string $identifier): array
    {
        return $this->records->find(Records::PAGE, [Records::IDENTIFIER_COLUMN => $identifier, 'sys_language_uid' => 0])[0];
    }

    private function field(string $marker, int $language = 0): array
    {
        $rows = $this->records->find(Records::FIELD, ['marker' => $marker, 'sys_language_uid' => $language, 'deleted' => 0]);
        self::assertCount(1, $rows, 'Feld ' . $marker);
        return $rows[0];
    }

    #[Test]
    public function createsPagesAndFieldsInOrder(): void
    {
        self::assertTrue($this->importer->import(1, self::contactForm()));

        $form = $this->records->byUid(Records::FORM, 1);
        self::assertSame('Kontakt', $form['title']);
        self::assertSame(2, $form['pages']);

        $contact = $this->page('kontakt');
        $thanks = $this->page('danke');
        self::assertSame([1, 1, 'Ihre Nachricht', 2, 87], [$contact['form'], $contact['sorting'], $contact['title'], $contact['fields'], $contact['pid']]);
        self::assertSame([2, 1], [$thanks['sorting'], $thanks['fields']]);

        self::assertSame([$contact['uid'], 1, 1, 87], [$this->field('name')['page'], $this->field('name')['sorting'], $this->field('name')['mandatory'], $this->field('name')['pid']]);
        self::assertSame([$contact['uid'], 2, 0], [$this->field('email')['page'], $this->field('email')['sorting'], $this->field('email')['mandatory']]);
        self::assertSame($thanks['uid'], $this->field('absenden')['page']);
    }

    #[Test]
    public function secondImportChangesNothing(): void
    {
        $this->importer->import(1, self::contactForm());
        $before = $this->records->rows;
        self::assertFalse($this->importer->import(1, self::contactForm()));
        self::assertSame($before, $this->records->rows);
    }

    #[Test]
    public function fieldKeepsItsUidWhenMovedToAnotherPage(): void
    {
        $this->importer->import(1, self::contactForm());
        $uid = $this->field('email')['uid'];

        $data = self::contactForm();
        $data['pages']['danke']['fields'] = ['email' => $data['pages']['kontakt']['fields']['email']] + $data['pages']['danke']['fields'];
        unset($data['pages']['kontakt']['fields']['email']);
        $this->importer->import(1, $data);

        self::assertSame($uid, $this->field('email')['uid']);
        self::assertSame($this->page('danke')['uid'], $this->field('email')['page']);
        self::assertSame(1, $this->field('email')['sorting']);
        self::assertSame(2, $this->field('absenden')['sorting']);
        self::assertSame(1, $this->page('kontakt')['fields']);
    }

    #[Test]
    public function removedPagesAndFieldsAreDeleted(): void
    {
        $this->importer->import(1, self::contactForm());
        $emailUid = $this->field('email')['uid'];
        $thanksUid = $this->page('danke')['uid'];
        $submitUid = $this->field('absenden')['uid'];

        $data = self::contactForm();
        unset($data['pages']['danke'], $data['pages']['kontakt']['fields']['email']);
        self::assertTrue($this->importer->import(1, $data));

        self::assertSame(1, $this->records->rows[Records::FIELD][$emailUid]['deleted']);
        self::assertSame(1, $this->records->rows[Records::PAGE][$thanksUid]['deleted']);
        self::assertSame(1, $this->records->rows[Records::FIELD][$submitUid]['deleted']);
        self::assertSame(1, $this->records->byUid(Records::FORM, 1)['pages']);
    }

    #[Test]
    public function existingRecordsWithoutIdentifierAreMatchedByTitleAndMarker(): void
    {
        $page = $this->records->add(Records::PAGE, ['form' => 1, 'title' => 'Ihre Nachricht', 'sorting' => 1]);
        $name = $this->records->add(Records::FIELD, ['page' => $page, 'marker' => 'name', 'type' => 'input', 'title' => 'Name']);

        $data = self::contactForm();
        $data['pages'] = ['ihre-nachricht' => $data['pages']['kontakt']];
        $this->importer->import(1, $data);

        self::assertSame($page, $this->page('ihre-nachricht')['uid']);
        self::assertSame($name, $this->field('name')['uid']);
    }

    #[Test]
    public function duplicateMarkersInTheDatabaseAreCleanedUp(): void
    {
        $page = $this->records->add(Records::PAGE, ['form' => 1, Records::IDENTIFIER_COLUMN => 'kontakt']);
        $first = $this->records->add(Records::FIELD, ['page' => $page, 'marker' => 'name', 'sorting' => 1]);
        $second = $this->records->add(Records::FIELD, ['page' => $page, 'marker' => 'name', 'sorting' => 2]);

        $this->importer->import(1, self::contactForm());

        self::assertSame($first, $this->field('name')['uid']);
        self::assertSame(1, $this->records->rows[Records::FIELD][$second]['deleted']);
    }

    #[Test]
    public function missingPropertiesAreResetToDefault(): void
    {
        $this->importer->import(1, self::contactForm());
        $uid = $this->field('name')['uid'];
        $this->records->update(Records::FIELD, $uid, ['css' => 'manuell', 'settings' => 'x']);

        self::assertTrue($this->importer->import(1, self::contactForm()));
        self::assertSame('', $this->records->rows[Records::FIELD][$uid]['css']);
        self::assertNull($this->records->rows[Records::FIELD][$uid]['settings']);
    }

    #[Test]
    public function recordsOfOtherFormsAndWorkspacesAreLeftAlone(): void
    {
        $this->records->add(Records::FORM, ['uid' => 2, 'title' => 'Anderes Formular']);
        $otherPage = $this->records->add(Records::PAGE, ['form' => 2, Records::IDENTIFIER_COLUMN => 'kontakt']);
        $otherField = $this->records->add(Records::FIELD, ['page' => $otherPage, 'marker' => 'name']);
        $draft = $this->records->add(Records::FIELD, ['page' => 0, 'marker' => 'name', 't3ver_wsid' => 1]);

        $this->importer->import(1, self::contactForm());

        self::assertSame(0, $this->records->rows[Records::PAGE][$otherPage]['deleted']);
        self::assertSame(0, $this->records->rows[Records::FIELD][$otherField]['deleted']);
        self::assertSame(0, $this->records->rows[Records::FIELD][$draft]['deleted']);
        self::assertSame('', $this->records->rows[Records::FIELD][$otherField]['type'], 'Feld des anderen Formulars unverändert');
        $ownPage = $this->records->live(Records::PAGE, ['form' => 1, 'sys_language_uid' => 0])[0];
        $ownName = $this->records->find(Records::FIELD, ['page' => $ownPage['uid'], 'marker' => 'name']);
        self::assertCount(1, $ownName);
        self::assertNotSame($otherField, $ownName[0]['uid']);
    }

    #[Test]
    public function translationsAreCreatedForAllPagesAndFields(): void
    {
        $data = self::contactForm();
        $data['localizations'] = [1 => [
            'title' => 'Contact',
            'pages' => ['kontakt' => ['title' => 'Your message']],
            'fields' => ['name' => ['title' => 'Your name']],
        ]];
        $this->importer->import(1, $data);

        $form = $this->records->find(Records::FORM, ['sys_language_uid' => 1, 'l10n_parent' => 1]);
        self::assertCount(1, $form);
        self::assertSame(['Contact', 2], [$form[0]['title'], $form[0]['pages']]);

        $pages = $this->records->find(Records::PAGE, ['sys_language_uid' => 1]);
        self::assertCount(2, $pages);
        self::assertSame($form[0]['uid'], $pages[0]['form']);
        self::assertSame('Your message', $pages[0]['title']);
        self::assertSame('Abschluss', $pages[1]['title']);

        $name = $this->field('name', 1);
        self::assertSame(['Your name', $this->field('name')['uid'], $pages[0]['uid'], 1], [$name['title'], $name['l10n_parent'], $name['page'], $name['mandatory']]);
        self::assertSame('E-Mail', $this->field('email', 1)['title']);
        self::assertSame($pages[1]['uid'], $this->field('absenden', 1)['page']);
    }

    #[Test]
    public function translationsUpdateInPlaceAndRemovedLanguagesAreDeleted(): void
    {
        $data = self::contactForm();
        $data['localizations'] = [1 => ['title' => 'Contact'], 2 => ['title' => 'Contatto']];
        $this->importer->import(1, $data);
        $english = $this->records->find(Records::FORM, ['sys_language_uid' => 1, 'deleted' => 0])[0]['uid'];

        $data['localizations'] = [1 => ['title' => 'Contact us']];
        $this->importer->import(1, $data);

        self::assertSame('Contact us', $this->records->byUid(Records::FORM, $english)['title']);
        self::assertSame([], $this->records->find(Records::FORM, ['sys_language_uid' => 2, 'deleted' => 0]));
        self::assertSame([], $this->records->find(Records::FIELD, ['sys_language_uid' => 2, 'deleted' => 0]));
        self::assertCount(3, $this->records->find(Records::FIELD, ['sys_language_uid' => 1, 'deleted' => 0]));
    }

    #[Test]
    public function deletedFieldTakesItsTranslationsAlong(): void
    {
        $data = self::contactForm();
        $data['localizations'] = [1 => []];
        $this->importer->import(1, $data);
        $translation = $this->field('email', 1)['uid'];

        unset($data['pages']['kontakt']['fields']['email']);
        $this->importer->import(1, $data);

        self::assertSame(1, $this->records->rows[Records::FIELD][$translation]['deleted']);
    }

    #[Test]
    public function emptyFormRemovesAllPages(): void
    {
        $this->importer->import(1, self::contactForm());
        $this->importer->import(1, ['title' => 'Leer']);
        self::assertSame([], $this->records->live(Records::PAGE, ['form' => 1]));
        self::assertSame(0, $this->records->byUid(Records::FORM, 1)['pages']);
    }

    public static function invalidDataProvider(): \Generator
    {
        $form = self::contactForm();

        $data = $form;
        $data['colour'] = 'rot';
        yield 'unbekannte Eigenschaft am Formular' => [$data, 'Formular: unbekannte Eigenschaft "colour"'];

        $data = $form;
        $data['pages']['kontakt']['layout'] = 'x';
        yield 'unbekannte Eigenschaft an der Seite' => [$data, 'Seite kontakt: unbekannte Eigenschaft "layout"'];

        $data = $form;
        $data['pages']['kontakt']['fields']['name']['lable'] = 'Tippfehler';
        yield 'unbekannte Eigenschaft am Feld' => [$data, 'Feld name: unbekannte Eigenschaft "lable"'];

        $data = $form;
        $data['pages']['kontakt']['fields']['name']['marker'] = 'anders';
        yield 'Marker als Eigenschaft' => [$data, 'unbekannte Eigenschaft "marker"'];

        $data = $form;
        $data['pages']['kontakt']['fields'] = ['Name' => ['type' => 'input']];
        yield 'Marker mit Großbuchstaben' => [$data, 'Ungültiger Marker "Name"'];

        $data = $form;
        $data['pages']['kontakt']['fields'] = ['e-mail' => ['type' => 'input']];
        yield 'Marker mit Bindestrich' => [$data, 'Ungültiger Marker "e-mail"'];

        $data = $form;
        $data['pages']['danke']['fields']['name'] = ['type' => 'input'];
        yield 'Marker doppelt über Seiten' => [$data, 'Marker "name" kommt mehrfach vor'];

        $data = $form;
        unset($data['pages']['kontakt']['fields']['name']['type']);
        yield 'Feld ohne type' => [$data, 'Feld "name" hat keinen type'];

        $data = $form;
        $data['pages']['kontakt']['fields']['name']['type'] = '';
        yield 'Feld mit leerem type' => [$data, 'Feld "name" hat keinen type'];

        $data = $form;
        $data['pages']['kontakt']['fields']['name'] = 'input';
        yield 'Feld als Text statt Zuordnung' => [$data, 'Feld name: Zuordnung (Schlüssel: Wert) erwartet'];

        $data = $form;
        $data['pages'] = [['title' => 'Liste statt Zuordnung']];
        yield 'Seiten als Liste' => [$data, 'pages: Zuordnung (Schlüssel: Wert) erwartet'];

        $data = $form;
        $data['pages'] = 'kontakt';
        yield 'Seiten als Text' => [$data, 'pages: Zuordnung (Schlüssel: Wert) erwartet'];

        $data = $form;
        $data['pages']['kontakt']['fields'] = [['type' => 'input']];
        yield 'Felder als Liste' => [$data, 'fields: Zuordnung (Schlüssel: Wert) erwartet'];

        $data = $form;
        $data['pages'] = ['mit leerzeichen' => ['title' => 'x']];
        yield 'Seiten-Bezeichner mit Leerzeichen' => [$data, 'Ungültiger Seiten-Bezeichner "mit leerzeichen"'];

        $data = $form;
        $data['pages']['kontakt']['fields']['name']['settings'] = ['a', 'b'];
        yield 'Liste als Wert' => [$data, 'Feld name, settings: Listen sind als Wert nicht erlaubt'];

        $data = $form;
        $data['localizations'] = ['en' => ['title' => 'Contact']];
        yield 'Sprache als Kürzel' => [$data, 'Sprache "en" in localizations muss eine Sprach-ID > 0 sein'];

        $data = $form;
        $data['localizations'] = [-1 => ['title' => 'Contact']];
        yield 'Sprache negativ' => [$data, 'Sprache "-1" in localizations muss eine Sprach-ID > 0 sein'];

        $data = $form;
        $data['localizations'] = [['title' => 'Contact']];
        yield 'Sprache 0 (Liste)' => [$data, 'localizations: Zuordnung (Schlüssel: Wert) erwartet'];

        $data = $form;
        $data['localizations'] = [1 => ['fields' => ['telefon' => ['title' => 'Phone']]]];
        yield 'Übersetzung für unbekanntes Feld' => [$data, 'localizations 1: unbekanntes Feld "telefon"'];

        $data = $form;
        $data['localizations'] = [1 => ['pages' => ['start' => ['title' => 'Start']]]];
        yield 'Übersetzung für unbekannte Seite' => [$data, 'localizations 1: unbekannte Seite "start"'];

        $data = $form;
        $data['localizations'] = [1 => ['fields' => ['name' => ['lable' => 'x']]]];
        yield 'Übersetzung mit unbekannter Eigenschaft' => [$data, 'localizations 1, Feld name: unbekannte Eigenschaft "lable"'];

        $data = $form;
        $data['localizations'] = [1 => 'Contact'];
        yield 'Übersetzung als Text' => [$data, 'localizations 1: Zuordnung (Schlüssel: Wert) erwartet'];
    }

    #[Test]
    #[DataProvider('invalidDataProvider')]
    public function invalidDataIsRejected(array $data, string $message): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage($message);
        $this->importer->import(1, $data);
    }

    #[Test]
    public function unknownFormIsRejected(): void
    {
        $this->expectExceptionMessage('Formular 99 nicht gefunden');
        $this->importer->import(99, self::contactForm());
    }

    #[Test]
    public function deletedFormIsRejected(): void
    {
        $this->records->update(Records::FORM, 1, ['deleted' => 1]);
        $this->expectExceptionMessage('Formular 1 nicht gefunden');
        $this->importer->import(1, self::contactForm());
    }

    #[Test]
    public function translatedFormIsRejected(): void
    {
        $this->records->add(Records::FORM, ['uid' => 5, 'sys_language_uid' => 1, 'l10n_parent' => 1]);
        $this->expectExceptionMessage('Formular 5 ist eine Übersetzung');
        $this->importer->import(5, self::contactForm());
    }
}
