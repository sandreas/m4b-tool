<?php

namespace M4bTool\Audio\TagTransfer;

use Exception;
use PHPUnit\Framework\TestCase;


class OpenPackagingFormatTest extends TestCase
{
    const OPF_CONTENT_ALL_ATTRIBUTES = <<<EOF
<?xml version='1.0' encoding='utf-8'?>
<package xmlns="http://www.idpf.org/2007/opf" unique-identifier="uuid_id" version="2.0">
    <metadata xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:opf="http://www.idpf.org/2007/opf">
        <dc:identifier opf:scheme="calibre" id="calibre_id">25</dc:identifier>
        <dc:identifier opf:scheme="uuid" id="uuid_id">5d8e3541-6d18-4383-ba7a-7d2fa3e6816e</dc:identifier>
        <dc:title>Astrophysics for People in a Hurry</dc:title>
        <dc:creator opf:file-as="Tyson, Neil DeGrasse" opf:role="aut">Neil Degrasse Tyson</dc:creator>
        <dc:contributor opf:file-as="calibre" opf:role="bkp">calibre (3.46.0) [https://calibre-ebook.com]</dc:contributor>
        <dc:date>2017-05-02T07:00:00+00:00</dc:date>
        <dc:description>&lt;p class="description"&gt;Over a year on the New York Times bestseller list and more than a million copies sold.  The essential universe, from our most celebrated and beloved astrophysicist. What is the nature of space and time? How do we fit within the universe? How does the universe fit within us? There’s no better guide through these mind-expanding questions than acclaimed astrophysicist and best-selling author Neil deGrasse Tyson. But today, few of us have time to contemplate the cosmos. So Tyson brings the universe down to Earth succinctly and clearly, with sparkling wit, in tasty chapters consumable anytime and anywhere in your busy day. While you wait for your morning coffee to brew, for the bus, the train, or a plane to arrive, Astrophysics for People in a Hurry will reveal just what you need to be fluent and ready for the next cosmic headlines: from the Big Bang to black holes, from quarks to quantum mechanics, and from the search for planets to the search for life in the universe.&lt;/p&gt;</dc:description>
        <dc:publisher>W. W. Norton &amp; Company</dc:publisher>
        <dc:identifier opf:scheme="GOOGLE">hx5DDQAAQBAJ</dc:identifier>
        <dc:identifier opf:scheme="ISBN">9780393609400</dc:identifier>
        <dc:language>eng</dc:language>
        <dc:subject>Science</dc:subject>
        <dc:subject>Physics</dc:subject>
        <dc:subject>Astrophysics</dc:subject>
        <dc:subject>Space Science</dc:subject>
        <meta content="{&quot;Neil Degrasse Tyson&quot;: &quot;&quot;}" name="calibre:author_link_map"/>
        <meta content="Test Series" name="calibre:series"/>
        <meta content="2" name="calibre:series_index"/>
        <meta content="10" name="calibre:rating"/>
        <meta content="2019-08-20T07:00:00+00:00" name="calibre:timestamp"/>
        <meta content="Custom Title: Astrophysics for People in a Hurry" name="calibre:title_sort"/>
    </metadata>
    <guide>
        <reference href="cover.jpg" title="Cover" type="cover"/>
    </guide>
</package>
EOF;

    const OPF_CONTENT_NO_ATTRIBUTES = <<<EOF
<?xml version='1.0' encoding='utf-8'?>
<package xmlns="http://www.idpf.org/2007/opf" unique-identifier="uuid_id" version="2.0">
    <metadata xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:opf="http://www.idpf.org/2007/opf">
        <dc:identifier opf:scheme="ISBN">9780393609400</dc:identifier>
    </metadata>
    <guide>
        <reference href="cover.jpg" title="Cover" type="cover"/>
    </guide>
</package>
EOF;


    /**
     * @throws Exception
     */
    public function testLoadAllAttributes()
    {
        $subject = new OpenPackagingFormat(static::OPF_CONTENT_ALL_ATTRIBUTES);
        $tag = $subject->load();
        $this->assertEquals("Astrophysics for People in a Hurry", $tag->title);
        $this->assertEquals("W. W. Norton & Company", $tag->publisher);
        $this->assertEquals("Over a year on the New York Times bestseller list and more than a million copies sold.  The essential universe, from our most celebrated and beloved astrophysicist. What is the nature of space and time? How do we fit within the universe? How does the universe fit within us? There’s no better guide through these mind-expanding questions than acclaimed astrophysicist and best-selling author Neil deGrasse Tyson. But today, few of us have time to contemplate the cosmos. So Tyson brings the universe down to Earth succinctly and clearly, with sparkling wit, in tasty chapters consumable anytime and anywhere in your busy day. While you wait for your morning coffee to brew, for the bus, the train, or a plane to arrive, Astrophysics for People in a Hurry will reveal just what you need to be fluent and ready for the next cosmic headlines: from the Big Bang to black holes, from quarks to quantum mechanics, and from the search for planets to the search for life in the universe.", $tag->longDescription);

        $this->assertEquals("Neil Degrasse Tyson", $tag->artist);
        $this->assertEquals(2017, $tag->year);
        $this->assertEquals("eng", $tag->language);

        $this->assertEquals("Science", $tag->genre);

        $this->assertEquals("Test Series", $tag->series);
        $this->assertEquals(2, $tag->seriesPart);
        $this->assertEquals("Custom Title: Astrophysics for People in a Hurry", $tag->sortTitle);
    }

    /**
     * @throws Exception
     */
    public function testLoadNoAttributes()
    {
        $subject = new OpenPackagingFormat(static::OPF_CONTENT_NO_ATTRIBUTES);
        $tag = $subject->load();

        $this->assertNull($tag->title);
        $this->assertNull($tag->publisher);
        $this->assertNull($tag->longDescription);
        $this->assertNull($tag->artist);
        $this->assertNull($tag->year);
        $this->assertNull($tag->language);

        $this->assertNull($tag->genre);

        $this->assertNull($tag->series);
        $this->assertNull($tag->seriesPart);
        $this->assertNull($tag->sortTitle);

    }
}
