<?php

namespace Sandreas\DateTime;

use DateTimeZone;
use Exception;
use PHPUnit\Framework\TestCase;

class DateTimeTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function testToString()
    {
        $subject = new DateTime("2019-12-01", new DateTimeZone("UTC"));
        $this->assertEquals("2019-12-01T00:00:00+00:00", (string)$subject);
    }

    /**
     * @throws Exception
     */
    public function testToJsonSerialize()
    {
        $subject = new DateTime("2019-12-01", new DateTimeZone("UTC"));
        $this->assertEquals('["2019-12-01T00:00:00+00:00"]', json_encode([$subject]));
    }
}
