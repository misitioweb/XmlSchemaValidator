<?php

namespace XmlSchemaValidatorTests;

use DOMDocument;
use XmlSchemaValidator\Schemas;
use XmlSchemaValidator\SchemaValidator;
use XmlSchemaValidator\SchemaValidatorException;

/** @covers \XmlSchemaValidator\SchemaValidator */
final class SchemaValidatorTest extends TestCase
{
    public function utilCreateValidator($file)
    {
        $location = $this->utilAssetLocation($file);
        if (! file_exists($location)) {
            $this->markTestSkipped("The file $location was not found");
        }
        $content = (string) file_get_contents($location);
        return new SchemaValidator($content);
    }

    public function testConstructUsingExistingDocument()
    {
        $document = new DOMDocument();
        $document->load($this->utilAssetLocation('books-valid.xml'));
        new SchemaValidator($document);
        $this->assertTrue(true, 'Expected no exception creating the schema validator using a DOMDocument');
    }

    public function testConstructorWithEmptyString()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('empty');
        new SchemaValidator('');
    }

    public function testValidatePreserveGlobalEnvironment()
    {
        error_reporting(E_NOTICE);
        libxml_use_internal_errors(false);
        try {
            new SchemaValidator(' this is not a valid xml ');
        } catch (SchemaValidatorException $exception) {
            unset($exception);
        }
        $this->assertSame(E_NOTICE, error_reporting());
        $this->assertSame(false, libxml_use_internal_errors());
    }

    public function testValidateWithNoSchema()
    {
        $validator = $this->utilCreateValidator('xml-without-schemas.xml');
        $this->assertTrue(
            $validator->validate(),
            'Validation without schemas and well formed document must return true'
        );
    }

    public function testValidateWithNotListedSchemaLocations()
    {
        $validator = $this->utilCreateValidator('not-listed-schemalocations.xml');
        $this->assertTrue($validator->validate());
    }

    public function testValidateWithNotEvenSchemaLocations()
    {
        $validator = $this->utilCreateValidator('not-even-schemalocations.xml');

        $this->expectException(SchemaValidatorException::class);
        $this->expectExceptionMessage('must have even number of URIs');
        $validator->validate();
    }

    public function testValidateValidXmlWithSchema()
    {
        $validator = $this->utilCreateValidator('books-valid.xml');

        $this->assertTrue($validator->validate());
    }

    public function testValidateValidXmlWithTwoSchemas()
    {
        $validator = $this->utilCreateValidator('ticket-valid.xml');

        $this->assertTrue($validator->validate());
        $this->assertEmpty($validator->getLastError());
    }

    public function testValidateInvalidXmlOnlyOneSchema()
    {
        $validator = $this->utilCreateValidator('books-invalid.xml');

        $this->assertFalse($validator->validate());
        $this->assertContains("The attribute 'serie' is required but missing", $validator->getLastError());
    }

    public function testValidateInvalidXmlFirstSchemas()
    {
        $validator = $this->utilCreateValidator('ticket-invalid-ticket.xml');

        $this->assertFalse($validator->validate());
        $this->assertContains("The attribute 'notes' is required but missing", $validator->getLastError());
    }

    public function testValidateInvalidXmlSecondSchemas()
    {
        $validator = $this->utilCreateValidator('ticket-invalid-book.xml');

        $this->assertFalse($validator->validate());
        $this->assertContains("The attribute 'serie' is required but missing", $validator->getLastError());
    }

    public function testValidateWithSchemasUsingRemote()
    {
        $validator = $this->utilCreateValidator('books-valid.xml');
        $schemas = new Schemas();
        $schemas->create('http://test.org/schemas/books', 'http://localhost:8999/xsd/books.xsd');
        $validator->validateWithSchemas($schemas);
        $this->assertTrue(true, 'validateWithSchemas did not throw any exception');
    }

    public function testValidateWithSchemasUsingLocal()
    {
        $validator = $this->utilCreateValidator('books-valid.xml');
        $schemas = new Schemas();
        $schemas->create(
            'http://test.org/schemas/books',
            str_replace('/', '\\', dirname(__DIR__)) . '/public/xsd/books.xsd' // simulate windows path
        );
        $validator->validateWithSchemas($schemas);
        $this->assertTrue(true, 'validateWithSchemas did not throw any exception');
    }

    public function testValidateWithEmptySchema()
    {
        $validator = $this->utilCreateValidator('books-valid.xml');
        $schemas = new Schemas();
        $schemas->create(
            'http://test.org/schemas/books',
            $this->utilAssetLocation('empty.xsd')
        );

        $this->expectException(SchemaValidatorException::class);
        $this->expectExceptionMessage('Failed to parse the XML resource');
        $validator->validateWithSchemas($schemas);
    }
}
