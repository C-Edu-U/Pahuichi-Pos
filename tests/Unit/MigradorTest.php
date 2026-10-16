<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Migrador;
use PHPUnit\Framework\TestCase;

final class MigradorTest extends TestCase
{
    public function testSeparaSentenciasYQuitaComentarios(): void
    {
        $sql = "-- comentario\nCREATE TABLE a (x INT);\r\n\nINSERT INTO a VALUES (1), (2);\nCREATE TRIGGER t BEFORE DELETE ON a FOR EACH ROW\n  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'no';\n";

        $this->assertSame([
            'CREATE TABLE a (x INT)',
            'INSERT INTO a VALUES (1), (2)',
            "CREATE TRIGGER t BEFORE DELETE ON a FOR EACH ROW\n  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'no'",
        ], Migrador::sentencias($sql));
    }
}
