<?php

namespace M4bTool\Audio\Tag;

use M4bTool\Audio\Tag;
use PHPUnit\Framework\TestCase;

class BookBeatJsonTest extends TestCase
{
    const SAMPLE_CONTENT = <<<EOT

EOT;

    public function testImprove()
    {
        $sampleContent = file_get_contents(__DIR__ . "/BookBeatJsonTest.json");
        $subject = new BookBeatJson($sampleContent);
        $actual = $subject->improve(new Tag);

        $this->assertEquals("Tochter der Flammen", $actual->title);
        $this->assertEquals("A. L. Knorr", $actual->artist);
        $this->assertEquals("Luca Lehnert", $actual->writer);
        $this->assertEquals("Tochter der Flammen", $actual->album);
        $this->assertEquals("https://prod-bb-images.akamaized.net/book-covers/coverimage-4066004041186-zebralution-2021-09-02.jpg?w=400", (string)$actual->cover);
        $this->assertEquals("Vier Freundinnen mit übersinnlichen Fähigkeiten - Die Töchter der Elemente

Band zwei der preisgekrönten Urban Fantasy Serie aus Kanada endlich auf Deutsch
- Kann unabhängig von Band 1 gelesen werden


Ein Kind des Feuers

Ganz allein reist die abenteuerlustige Saxony den Sommer über nach Venedig. Sie soll sich dort als Au-pair um den kleinen Isaia kümmern. Allerdings hat ihre Gastfamilie Saxony verschwiegen, dass der Junge an einer mysteriösen Krankheit leidet: Isaias Stirn wird in Stresssituationen heiß wie Kohle und in seinen Augen strahlt eine unheimliche Glut.

Doch Isaia ist nicht das einzige Mysterium, dem Saxony in Venedig begegnet. Sie trifft den undurchschaubaren Dante, den Sohn eines Mafiabosses. Doch was als aufregende Liebschaft beginnt, wird bald gefährlich. In größter Not eilt ausgerechnet der kranke Isaia Saxony zu Hilfe und überträgt eine einzigartige Fähigkeit auf sie.

Mit ihren neuen Kräften muss Saxony Dante und entgegentreten. Doch sie fürchtet sich nicht mehr vor ihm.
Denn sie ist eine Tochter des Feuers.
Die Tochter der Flammen.

Über die Töchter der Elemente - USA Today Bestseller


Targa, Saxony, Georjayna und Akiko sind beste Freundinnen. Doch jede hat ein Geheimnis,
das sie mit niemandem teilen kann. Denn sie sind die Töchter der Elemente - magische
Wesen, die erst noch entdecken müssen, wie viel Macht in ihnen schlummert.
Nach diesem Sommer wird nichts mehr so sein wie zuvor.", $actual->description);

        $this->assertEquals("German", $actual->language);

        $this->assertEquals("Der Ursprung der Elemente", $actual->series);
        $this->assertEquals("2", $actual->seriesPart);
    }

}
