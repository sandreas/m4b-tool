<?php

namespace M4bTool\Audio\Tag;

use M4bTool\Audio\Tag;
use PHPUnit\Framework\TestCase;

class BuchhandelJsonTest extends TestCase
{
    const SAMPLE_CONTENT = <<<EOT
{"data":{"type":"productDetails","id":"9783838792330","attributes":{"extent":null,"illustrations":[],"mainLanguages":["ger"],"biographicalNotes":[],"relatedProducts":[{"title":"Strange the Dreamer - Der Junge, der träumte","identifier":"9783846600856","productGroup":"book","fileFormat":null},{"title":"Strange the Dreamer","identifier":"9783846601372","productGroup":"book","fileFormat":null},{"title":"Strange the Dreamer","identifier":"9783751716499","productGroup":"ebook","fileFormat":"029"},{"title":"Strange the Dreamer - Der Junge, der träumte","identifier":"9783785780145","productGroup":"audio","fileFormat":null}],"edition":{"text":"1. Aufl. 2019","number":"1"},"mediaFiles":[],"medium":null,"title":"Strange the Dreamer - Der Junge, der träumte","unpricedItemCode":null,"hasAdvertising":null,"contributorNotes":[],"publicationFrequency":null,"numPages":null,"zisSubjectGroups":null,"contributor":null,"subTitle":"Teil 1. Ungekürzt.","containedItem":[],"collections":[{"name":"Strange the Dreamer","sequence":"1","identifier":"AAYMT76"}],"subLanguages":[],"mainDescriptions":[{"description":"<p>Lass dich hineinziehen in eine Welt voller Träume<br /><br />Lazlo Strange liebt es, Geheimnisse zu ergründen und Abenteuer zu erleben. Allerdings nur zwischen den Seiten seiner Bücher, denn ansonsten erlebt der junge Bibliothekar nur wenig Aufregendes. Er ist ein Träumer und schwelgt am liebsten in den Geschichten um die sagenumwobene Stadt Weep - ein mysteriöser Ort, um den sich zahlreiche Geheimnisse ranken. Eines Tages werden Freiwillige für eine Reise nach Weep gesucht, und für Lazlo steht sofort fest, dass er sich der Gruppe anschließen muss. Ohne zu wissen, was sie in der verborgenen Stadt erwartet, machen sie sich auf den Weg. Wird Lazlos Traum nun endlich Wirklichkeit?<br /><br />Die international gefeierte Reihe der Bestsellerautorin Laini Taylor endlich auf Deutsch<br /></p>","containsHTML":true}],"productIcon":"audio","titleShort":null,"prices":[{"value":11.99,"country":"DE","currency":"EUR","state":"02","type":"02","taxRate":"R","description":null,"minQuantity":null,"provisional":false,"typeQualifier":null,"priceReference":false,"fixedRetailPrice":false}],"publicationDate":"20190930","productType":"abook","measurements":"","identifier":"978-3-8387-9233-0","productFileFormat":null,"pricesAT":[{"value":11.99,"country":"AT","currency":"EUR","state":"02","type":"02","taxRate":"R","description":null,"minQuantity":null,"provisional":false,"typeQualifier":null,"priceReference":false,"fixedRetailPrice":false}],"oesbNr":null,"originalLanguage":"eng","coverUrl":"https://www.buchhandel.de/cover/9783838792330/9783838792330-cover-m.jpg","specialPriceText":null,"productFormId":"AJ","originalTitle":null,"publisher":"Lübbe Audio","contributors":[{"name":"Taylor, Laini","type":"A01","biographicalNote":null},{"name":"Franko, James","type":"A01","biographicalNote":null},{"name":"Pliquet, Moritz","type":"E07","biographicalNote":null},{"name":"Raimer-Nolte, Ulrike","type":"B06","biographicalNote":null}]},"relationships":{},"links":{"self":"/jsonapi/productDetails/9783838792330"}},"included":[]}
EOT;

    public function testImprove()
    {
        $subject = new BuchhandelJson(static::SAMPLE_CONTENT);
        $actual = $subject->improve(new Tag);

        $this->assertEquals("Strange the Dreamer - Der Junge, der träumte", $actual->title);
        $this->assertEquals("James Franko, Laini Taylor", $actual->artist);
        $this->assertEquals("Moritz Pliquet", $actual->writer);
        $this->assertEquals("Strange the Dreamer - Der Junge, der träumte", $actual->album);
        $this->assertEquals("https://www.buchhandel.de/cover/9783838792330/9783838792330-cover-m.jpg", (string)$actual->cover);
        $this->assertEquals("Lass dich hineinziehen in eine Welt voller Träume

Lazlo Strange liebt es, Geheimnisse zu ergründen und Abenteuer zu erleben. Allerdings nur zwischen den Seiten seiner Bücher, denn ansonsten erlebt der junge Bibliothekar nur wenig Aufregendes. Er ist ein Träumer und schwelgt am liebsten in den Geschichten um die sagenumwobene Stadt Weep - ein mysteriöser Ort, um den sich zahlreiche Geheimnisse ranken. Eines Tages werden Freiwillige für eine Reise nach Weep gesucht, und für Lazlo steht sofort fest, dass er sich der Gruppe anschließen muss. Ohne zu wissen, was sie in der verborgenen Stadt erwartet, machen sie sich auf den Weg. Wird Lazlos Traum nun endlich Wirklichkeit?

Die international gefeierte Reihe der Bestsellerautorin Laini Taylor endlich auf Deutsch
", $actual->description);

        $this->assertEquals("ger", $actual->language);

        $this->assertEquals("Strange the Dreamer", $actual->series);
        $this->assertEquals("1", $actual->seriesPart);
    }

}
