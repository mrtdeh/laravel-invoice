<?php

namespace NeptuneSoftware\Invoicable\Feature;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use NeptuneSoftware\Invoicable\AbstractTestCase;
use NeptuneSoftware\Invoicable\CustomerTestModel;
use NeptuneSoftware\Invoicable\Interfaces\InvoiceServiceInterface;
use NeptuneSoftware\Invoicable\ProductTestModel;

class InvoiceTest extends AbstractTestCase
{
    use DatabaseMigrations;

    private $invoice;
    private $productModel;
    private $customerModel;
    private $service;


    public function setUp(): void
    {
        parent::setUp();
        $this->customerModel = new CustomerTestModel();
        $this->customerModel->save();
        $this->productModel = new ProductTestModel();
        $this->productModel->save();
        $this->service = $this->app->make(InvoiceServiceInterface::class);
        $this->invoice = $this->service->create($this->customerModel)->getInvoice()->fresh();
    }


    /** @test */
    public function canCreateInvoice()
    {
        $this->assertEquals("0", (string)$this->invoice->total);
        $this->assertEquals("0", (string)$this->invoice->tax);
        $this->assertEquals('TRY', $this->invoice->currency);
        $this->assertEquals("concept", $this->invoice->status);
        $this->assertNotNull($this->invoice->reference);
    }

    /** @test */
    public function canAddAmountExclTaxToInvoice()
    {
        $this->invoice = $this->service->create($this->customerModel)->getInvoice();

        $invoice = $this->service->addAmountExclTax($this->productModel, 100, 'Some description', 0.21);
        $invoice = $this->service->addAmountExclTax($this->productModel, 100, 'Some description', 0.21);

        $this->assertEquals("242", (string)$invoice->total);
        $this->assertEquals("42", (string)$invoice->tax);
    }

    /** @test */
    public function canAddAmountInclTaxToInvoice()
    {
        $this->invoice = $this->service->create($this->customerModel)->getInvoice();

        $invoice = $this->service->addAmountInclTax($this->productModel, 121, 'Some description', 0.21);
        $invoice = $this->service->addAmountInclTax($this->productModel, 121, 'Some description', 0.21);

        $this->assertEquals("242", (string)$invoice->total);
        $this->assertEquals("42", (string)$invoice->tax);
    }

    /** @test */
    public function canHandleNegativeAmounts()
    {
        $this->invoice = $this->service->create($this->customerModel)->getInvoice();

        $invoice = $this->service->addAmountInclTax($this->productModel, 121, 'Some description', 0.21);
        $invoice = $this->service->addAmountInclTax($this->productModel, -121, 'Some negative amount description', 0.21);

        $this->assertEquals("0", (string)$invoice->total);
        $this->assertEquals("0", (string)$invoice->tax);
    }

    /** @test */
    public function hasUniqueReference()
    {
        $references = array_map(function () {
            return $this->service->create($this->customerModel)->getInvoice()->reference;
        }, range(1, 100));

        $this->assertCount(100, array_unique($references));
    }

    /** @test */
    public function canGetInvoiceView()
    {
        $this->service->addAmountInclTax($this->productModel, 121, 'Some description', 0.21);
        $this->service->addAmountInclTax($this->productModel, 121, 'Some description', 0.21);
        $view = $this->service->view();
        $rendered = $view->render(); // fails if view cannot be rendered
        $this->assertTrue(true);
    }

    /** @test */
    public function canGetInvoicePdf()
    {
        $this->service->addAmountInclTax($this->productModel, 121, 'Some description', 0.21);
        $this->service->addAmountInclTax($this->productModel, 121, 'Some description', 0.21);
        $pdf = $this->service->pdf();  // fails if pdf cannot be rendered
        $this->assertTrue(true);
    }

    /** @test */
    public function canDownloadInvoicePdf()
    {
        $this->service->addAmountInclTax($this->productModel, 121, 'Some description', 0.21);
        $this->service->addAmountInclTax($this->productModel, 121, 'Some description', 0.21);
        $download = $this->service->download(); // fails if pdf cannot be rendered
        $this->assertTrue(true);
    }

    /** @test */
    public function canFindByReference()
    {
        $this->assertEquals($this->invoice->id, $this->service->findByReference($this->invoice->reference)->id);
    }

    /** @test */
    public function canFindByReferenceOrFail()
    {
        $this->assertEquals($this->invoice->id, $this->service->findByReferenceOrFail($this->invoice->reference)->id);
    }

    /** @test */
    public function canFindByReferenceOrFailThrowsExceptionForNonExistingReference()
    {
        $this->expectException('Illuminate\Database\Eloquent\ModelNotFoundException');
        $this->service->findByReferenceOrFail('non-existing-reference');
    }

    /** @test */
    public function canAccessRelated()
    {
        // Check if correctly set on invoice
        $this->assertEquals(CustomerTestModel::class, $this->invoice->related_type);
        $this->assertEquals($this->customerModel->id, $this->invoice->related_id);

        // Check if related is accessible
        $this->assertNotNull($this->invoice->related);
        $this->assertEquals(CustomerTestModel::class, get_class($this->invoice->related));
        $this->assertEquals($this->customerModel->id, $this->invoice->related->id);
    }

    /**
     * @test
     */
    /*
     *
    public function ifIsFreeEqualToTrueShouldBeAmountEqualToZero()
    {
        $this->invoice = $this->service->create($this->customerModel)->getInvoice();

        $invoice = $this->service->$this->setInvoiceable($this->productModel)->addAmountExclTaxWithAllValues(0, 'Some description', 0);
        $invoice = $this->service->$this->setInvoiceable($this->productModel)->addAmountExclTaxWithAllValues(121, 'Some description', 0.21);
        $this->assertEquals(0, $invoice->lines()->first()->amount);
        $this->assertGreaterThan(0, $invoice->lines->last()->amount);
    }
    */

    /**
     * @test
     */
    /*
    public function ifIsFreeEqualToFalseShouldBeAmountGreaterThanZero()
    {
        $invoicable_id = $this->productModel->id;
        $invoicable_type = get_class($this->productModel);
        $this->invoice = $this->service->create($this->customerModel)->getInvoice();

        $invoice = $this->service->addAmountExclTax(0, 'Some description', $invoicable_id, $invoicable_type, 0);
        $invoice = $this->service->addAmountExclTax(121, 'Some description', $invoicable_id, $invoicable_type, 0.21);

        $this->assertGreaterThan(0, $invoice->lines->last()->amount);
    }
    */

    /**
     * @test
     */
    /*
    public function ifIsComplimentaryEqualToTrueShouldBeAmountEqualToZero()
    {
        $invoicable_id = $this->productModel->id;
        $invoicable_type = get_class($this->productModel);
        $this->invoice = $this->service->create($this->customerModel)->getInvoice();

        $invoice = $this->service->addAmountExclTaxWithAllValues(
            0,
            'Some description',
            $invoicable_id,
            $invoicable_type,
            false,
            true,
            0
        );
        $invoice = $this->service->addAmountExclTaxWithAllValues(
            121,
            'Some description',
            $invoicable_id,
            $invoicable_type,
            false,
            false,
            0.21
        );

        $this->assertEquals(0, $invoice->lines()->first()->amount);
    }
    */

    /**
     * @test
     */
    /*
    public function ifIsComplimentaryEqualToFalseShouldBeAmountGreaterThanZero()
    {
        $invoicable_id = $this->productModel->id;
        $invoicable_type = get_class($this->productModel);
        $this->invoice = $this->service->create($this->customerModel)->getInvoice();

        $invoice = $this->service->addAmountExclTax(0, 'Some description', $invoicable_id, $invoicable_type, 0);
        $invoice = $this->service->addAmountExclTax(121, 'Some description', $invoicable_id, $invoicable_type, 0.21);

        $this->assertGreaterThan(0, $invoice->lines->last()->amount);
    }
    */
}
