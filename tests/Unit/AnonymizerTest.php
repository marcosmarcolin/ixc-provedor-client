<?php

declare(strict_types=1);

namespace Marcolin\IxcProvedor\Tests\Unit;

use Marcolin\IxcProvedor\Tools\Anonymizer;
use PHPUnit\Framework\TestCase;

final class AnonymizerTest extends TestCase
{
    public function testReplacesPersonalDataKeepingKeysAndFormats(): void
    {
        $result = (new Anonymizer())->anonymize([
            'page' => '1',
            'total' => '2',
            'registros' => [[
                'id' => '1234',
                'razao' => 'Maria da Silva',
                'cnpj_cpf' => '123.456.789-00',
                'email' => 'maria@provedor.com.br',
                'data_nascimento' => '1990-05-17',
                'ultima_atualizacao' => '2026-10-01 12:30:00',
                'data_cadastro' => '0000-00-00',
                'ativo' => 'S',
                'obs' => '',
                'valor' => '149.90',
            ]],
        ]);

        self::assertIsArray($result);
        self::assertSame('1', $result['page']);
        self::assertSame('2', $result['total']);
        self::assertIsArray($result['registros']);
        $row = $result['registros'][0];
        self::assertIsArray($row);
        self::assertMatchesRegularExpression('/^1\d{3}$/', self::string($row['id']));
        self::assertNotSame('1234', $row['id']);
        self::assertMatchesRegularExpression('/^anon-[0-9a-f]{8}$/', self::string($row['razao']));
        self::assertMatchesRegularExpression('/^anon-[0-9a-f]{8}$/', self::string($row['cnpj_cpf']));
        self::assertMatchesRegularExpression('/^anon-[0-9a-f]{8}@example\.com$/', self::string($row['email']));
        self::assertSame('2000-01-01', $row['data_nascimento']);
        self::assertSame('2000-01-01 00:00:00', $row['ultima_atualizacao']);
        self::assertSame('0000-00-00', $row['data_cadastro']);
        self::assertSame('S', $row['ativo']);
        self::assertSame('', $row['obs']);
        self::assertSame('1.00', $row['valor']);
    }

    public function testEqualValuesStayEqualSoReferencesStillMatch(): void
    {
        $anonymizer = new Anonymizer();

        self::assertSame($anonymizer->anonymize('987'), $anonymizer->anonymize('987'));
        self::assertNotSame($anonymizer->anonymize('987'), $anonymizer->anonymize('988'));
    }

    public function testKeepsTheApiStatusAndMessage(): void
    {
        $body = ['type' => 'error', 'message' => 'Recurso tabela_x não está disponível!'];

        self::assertSame($body, (new Anonymizer())->anonymize($body));
    }

    public function testKeepsNonStringScalars(): void
    {
        self::assertSame([1, 2.5, true, null], (new Anonymizer())->anonymize([1, 2.5, true, null]));
    }

    /**
     * @param mixed $value
     */
    private static function string($value): string
    {
        self::assertIsString($value);

        return $value;
    }
}
