<?php

namespace Tests\Unit\Validation;

use Tests\TestCase;
use Zephyr\Validation\ValidationSchema;
use Zephyr\Validation\SchemaType\StringType;

class ValidationSchemaTest extends TestCase
{
    public function test_valid_data_passes()
    {
        $schema = ValidationSchema::make()->shape([ //
            'email' => ValidationSchema::make()->string()->required()->email(), //
        ]);
        
        $result = $schema->validate(['email' => 'test@example.com']);

        $this->assertFalse($result->hasErrors()); //
        $this->assertEquals(['email' => 'test@example.com'], $result->getValidData()); //
    }

    public function test_required_rule_fails()
    {
        $schema = ValidationSchema::make()->shape([
            'name' => ValidationSchema::make()->string()->required(),
        ]);
        
        $result = $schema->validate(['name' => '']);
        $this->assertTrue($result->hasErrors());
        $this->assertStringContainsString('zorunludur', $result->getFirstError()); //
    }

    /**
     * Rapor #3'teki 'null bypass' açığının kapatıldığını test eder.
     */
    public function test_null_value_fails_on_non_nullable_string()
    {
        $schema = ValidationSchema::make()->shape([
            // Bu alan 'required' değil ama 'nullable' da değil.
            'bio' => ValidationSchema::make()->string()->min(10),
        ]);
        
        $result = $schema->validate(['bio' => null]);

        // Yamamız sayesinde, null değerin string olmadığını belirten bir hata almalıyız
        $this->assertTrue($result->hasErrors());
        $this->assertStringContainsString('metin tipinde olmalıdır (null olamaz)', $result->getFirstError());
    }

    /**
     * Rapor #3'teki 'null bypass' açığının kapatıldığını test eder (nullable case).
     */
    public function test_null_value_passes_on_nullable_string()
    {
        $schema = ValidationSchema::make()->shape([
            'bio' => ValidationSchema::make()->string()->min(10)->nullable(), //
        ]);
        
        // 'bio' null, ama alan 'nullable' olduğu için hata vermemeli
        $result = $schema->validate(['bio' => null]);
        
        $this->assertFalse($result->hasErrors());
    }
}