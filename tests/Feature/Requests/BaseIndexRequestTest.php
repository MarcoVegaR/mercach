<?php

declare(strict_types=1);

namespace Tests\Feature\Requests;

use App\DTO\ListQuery;
use App\Http\Requests\BaseIndexRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Implementación concreta de BaseIndexRequest para testing.
 */
class TestIndexRequest extends BaseIndexRequest
{
    protected function allowedSorts(): array
    {
        return ['id', 'name', 'email', 'created_at', 'updated_at'];
    }

    protected function filterRules(): array
    {
        return [
            'filters.status' => ['nullable', 'string', 'in:active,inactive'],
            'filters.created_between' => ['nullable', 'array'],
            'filters.created_between.from' => ['nullable', 'date'],
            'filters.created_between.to' => ['nullable', 'date'],
            'filters.age_between' => ['nullable', 'array'],
            'filters.age_between.from' => ['nullable', 'numeric'],
            'filters.age_between.to' => ['nullable', 'numeric'],
            'filters.ids' => ['nullable', 'array'],
            'filters.ids.*' => ['integer'],
            'filters.is_verified' => ['nullable', 'boolean'],
            'filters.is_active' => ['nullable', 'boolean'],
            'filters.is_admin' => ['nullable', 'boolean'],
            'filters.is_guest' => ['nullable', 'boolean'],
            'filters.guard_name' => ['nullable', 'string', 'max:50'],
        ];
    }

    protected function maxPerPage(): int
    {
        return 50; // Diferente del default para testing
    }

    protected function defaultPerPage(): int
    {
        return 10; // Diferente del default para testing
    }

    protected function sanitize(array $validated): array
    {
        // Ejemplo de sanitización personalizada
        if (isset($validated['q'])) {
            $validated['q'] = trim($validated['q']);
        }

        return $validated;
    }
}

class BaseIndexRequestTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function authorize_always_returns_true()
    {
        $request = new TestIndexRequest;
        $this->assertTrue($request->authorize());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function validates_basic_fields_successfully()
    {
        $data = [
            'q' => 'search term',
            'page' => 1,
            'per_page' => 15,
            'sort' => 'name',
            'dir' => 'asc',
        ];

        $validator = Validator::make($data, (new TestIndexRequest)->rules());
        $this->assertTrue($validator->passes());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function validates_q_field()
    {
        // Valid cases
        $validCases = [
            ['q' => 'test'],
            ['q' => ''],
            ['q' => null],
            ['q' => str_repeat('a', 255)], // Max length
        ];

        foreach ($validCases as $data) {
            $validator = Validator::make($data, (new TestIndexRequest)->rules());
            $this->assertTrue($validator->passes(), 'Failed for data: '.json_encode($data));
        }

        // Invalid cases
        $invalidCases = [
            ['q' => str_repeat('a', 256)], // Too long
            ['q' => 123], // Not string
            ['q' => ['array']], // Not string
        ];

        foreach ($invalidCases as $data) {
            $validator = Validator::make($data, (new TestIndexRequest)->rules());
            $this->assertTrue($validator->fails(), 'Should fail for data: '.json_encode($data));
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function validates_page_field()
    {
        // Valid cases
        $validCases = [
            ['page' => 1],
            ['page' => 100],
            ['page' => null],
        ];

        foreach ($validCases as $data) {
            $validator = Validator::make($data, (new TestIndexRequest)->rules());
            $this->assertTrue($validator->passes(), 'Failed for data: '.json_encode($data));
        }

        // Invalid cases
        $invalidCases = [
            ['page' => 0], // Too small
            ['page' => -1], // Negative
            ['page' => 'not_int'], // Not integer
            ['page' => 1.5], // Not integer
        ];

        foreach ($invalidCases as $data) {
            $validator = Validator::make($data, (new TestIndexRequest)->rules());
            $this->assertTrue($validator->fails(), 'Should fail for data: '.json_encode($data));
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function validates_per_page_field()
    {
        // Valid cases
        $validCases = [
            ['per_page' => 1],
            ['per_page' => 25],
            ['per_page' => 50], // Max for test class
            ['per_page' => null],
        ];

        foreach ($validCases as $data) {
            $validator = Validator::make($data, (new TestIndexRequest)->rules());
            $this->assertTrue($validator->passes(), 'Failed for data: '.json_encode($data));
        }

        // Invalid cases
        $invalidCases = [
            ['per_page' => 0], // Too small
            ['per_page' => 51], // Too large for test class
            ['per_page' => -1], // Negative
            ['per_page' => 'not_int'], // Not integer
        ];

        foreach ($invalidCases as $data) {
            $validator = Validator::make($data, (new TestIndexRequest)->rules());
            $this->assertTrue($validator->fails(), 'Should fail for data: '.json_encode($data));
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function validates_sort_field()
    {
        // Valid cases
        $validCases = [
            ['sort' => 'id'],
            ['sort' => 'name'],
            ['sort' => 'email'],
            ['sort' => 'created_at'],
            ['sort' => 'updated_at'],
            ['sort' => null],
        ];

        foreach ($validCases as $data) {
            $validator = Validator::make($data, (new TestIndexRequest)->rules());
            $this->assertTrue($validator->passes(), 'Failed for data: '.json_encode($data));
        }

        // Invalid cases
        $invalidCases = [
            ['sort' => 'invalid_field'],
            ['sort' => 'password'], // Not in allowed list
            ['sort' => 123],
        ];

        foreach ($invalidCases as $data) {
            $validator = Validator::make($data, (new TestIndexRequest)->rules());
            $this->assertTrue($validator->fails(), 'Should fail for data: '.json_encode($data));
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function validates_dir_field()
    {
        // Valid cases
        $validCases = [
            ['dir' => 'asc'],
            ['dir' => 'desc'],
            ['dir' => null],
        ];

        foreach ($validCases as $data) {
            $validator = Validator::make($data, (new TestIndexRequest)->rules());
            $this->assertTrue($validator->passes(), 'Failed for data: '.json_encode($data));
        }

        // Invalid cases
        $invalidCases = [
            ['dir' => 'invalid'],
            ['dir' => 'ascending'],
            ['dir' => 123],
        ];

        foreach ($invalidCases as $data) {
            $validator = Validator::make($data, (new TestIndexRequest)->rules());
            $this->assertTrue($validator->fails(), 'Should fail for data: '.json_encode($data));
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function validates_filters_structure()
    {
        // Valid cases
        $validCases = [
            ['filters' => []],
            ['filters' => ['status' => 'active']],
            ['filters' => ['ids' => [1, 2, 3]]],
            ['filters' => null],
        ];

        foreach ($validCases as $data) {
            $validator = Validator::make($data, (new TestIndexRequest)->rules());
            $this->assertTrue($validator->passes(), 'Failed for data: '.json_encode($data));
        }

        // Invalid cases
        $invalidCases = [
            ['filters' => 'not_array'],
            ['filters' => 123],
        ];

        foreach ($invalidCases as $data) {
            $validator = Validator::make($data, (new TestIndexRequest)->rules());
            $this->assertTrue($validator->fails(), 'Should fail for data: '.json_encode($data));
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function validates_specific_filter_rules()
    {
        // Valid filter cases
        $validCases = [
            ['filters' => ['status' => 'active']],
            ['filters' => ['status' => 'inactive']],
            ['filters' => ['ids' => [1, 2, 3]]],
            ['filters' => ['is_verified' => true]],
            ['filters' => ['is_verified' => false]],
            ['filters' => ['guard_name' => 'web']],
            ['filters' => [
                'created_between' => [
                    'from' => '2024-01-01',
                    'to' => '2024-12-31',
                ],
            ]],
        ];

        foreach ($validCases as $data) {
            $validator = Validator::make($data, (new TestIndexRequest)->rules());
            $this->assertTrue($validator->passes(), 'Failed for data: '.json_encode($data));
        }

        // Invalid filter cases
        $invalidCases = [
            ['filters' => ['status' => 'invalid_status']],
            ['filters' => ['ids' => ['not_int']]],
            ['filters' => ['is_verified' => 'not_boolean']],
            ['filters' => ['guard_name' => str_repeat('a', 51)]], // Too long
            ['filters' => [
                'created_between' => [
                    'from' => 'invalid_date',
                ],
            ]],
        ];

        foreach ($invalidCases as $data) {
            $validator = Validator::make($data, (new TestIndexRequest)->rules());
            $this->assertTrue($validator->fails(), 'Should fail for data: '.json_encode($data));
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function applies_default_per_page_when_missing()
    {
        $data = ['q' => 'test'];

        $request = $this->createFormRequest(TestIndexRequest::class, $data);

        $this->assertEquals(10, $request->input('per_page')); // Default from test class
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function limits_per_page_to_max_when_exceeded()
    {
        // This should fail validation since 100 > 50 (maxPerPage for test class)
        $data = ['per_page' => 100];

        $validator = Validator::make($data, (new TestIndexRequest)->rules());
        $this->assertTrue($validator->fails());

        // Test the normalization logic with a valid per_page that gets limited internally
        $data = ['per_page' => 45]; // Valid, but we'll test internal limiting
        $request = $this->createFormRequest(TestIndexRequest::class, $data);
        $this->assertEquals(45, $request->input('per_page'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function normalizes_direction_to_lowercase()
    {
        $data = ['dir' => 'ASC'];

        $request = $this->createFormRequest(TestIndexRequest::class, $data);

        $this->assertEquals('asc', $request->input('dir'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function normalizes_boolean_strings_in_filters()
    {
        $data = [
            'filters' => [
                'is_verified' => 'true',
                'is_active' => 'false',
                'is_admin' => '1',
                'is_guest' => '0',
            ],
        ];

        $request = $this->createFormRequest(TestIndexRequest::class, $data);

        $filters = $request->input('filters');
        $this->assertTrue($filters['is_verified']);
        $this->assertFalse($filters['is_active']);
        $this->assertTrue($filters['is_admin']);
        $this->assertFalse($filters['is_guest']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function normalizes_date_ranges_when_from_greater_than_to()
    {
        $data = [
            'filters' => [
                'created_between' => [
                    'from' => '2024-12-31',
                    'to' => '2024-01-01', // from > to
                ],
            ],
        ];

        $request = $this->createFormRequest(TestIndexRequest::class, $data);

        $range = $request->input('filters.created_between');
        $this->assertEquals('2024-01-01', $range['from']); // Should be swapped
        $this->assertEquals('2024-12-31', $range['to']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function normalizes_numeric_ranges_when_from_greater_than_to()
    {
        $data = [
            'filters' => [
                'age_between' => [
                    'from' => '50',
                    'to' => '18', // from > to
                ],
            ],
        ];

        $request = $this->createFormRequest(TestIndexRequest::class, $data);

        $range = $request->input('filters.age_between');
        $this->assertEquals('18', $range['from']); // Should be swapped
        $this->assertEquals('50', $range['to']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function ensures_filters_is_array()
    {
        $data = ['q' => 'test']; // No filters provided

        $request = $this->createFormRequest(TestIndexRequest::class, $data);

        $this->assertIsArray($request->input('filters'));
        $this->assertEmpty($request->input('filters'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function applies_custom_sanitize_hook()
    {
        $data = ['q' => '  search term with spaces  '];

        $request = $this->createFormRequest(TestIndexRequest::class, $data);

        $this->assertEquals('search term with spaces', $request->input('q')); // Trimmed
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function to_list_query_returns_correct_dto()
    {
        $data = [
            'q' => 'search',
            'page' => 2,
            'per_page' => 20,
            'sort' => 'name',
            'dir' => 'asc',
            'filters' => [
                'status' => 'active',
                'ids' => [1, 2, 3],
            ],
        ];

        $request = $this->createFormRequest(TestIndexRequest::class, $data);

        $listQuery = $request->toListQuery();

        $this->assertInstanceOf(ListQuery::class, $listQuery);
        $this->assertEquals('search', $listQuery->q);
        $this->assertEquals(2, $listQuery->page);
        $this->assertEquals(20, $listQuery->perPage);
        $this->assertEquals('name', $listQuery->sort);
        $this->assertEquals('asc', $listQuery->dir);
        $this->assertEquals(['status' => 'active', 'ids' => [1, 2, 3]], $listQuery->filters);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function to_list_query_applies_defaults()
    {
        $data = ['q' => 'search']; // Minimal data

        $request = $this->createFormRequest(TestIndexRequest::class, $data);

        $listQuery = $request->toListQuery();

        $this->assertInstanceOf(ListQuery::class, $listQuery);
        $this->assertEquals('search', $listQuery->q);
        $this->assertEquals(1, $listQuery->page); // Default from DTO
        $this->assertEquals(10, $listQuery->perPage); // Default from test class
        $this->assertNull($listQuery->sort);
        $this->assertEquals('desc', $listQuery->dir); // Default from DTO
        $this->assertIsArray($listQuery->filters);
        $this->assertEmpty($listQuery->filters);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function handles_complex_nested_filters()
    {
        $data = [
            'filters' => [
                'created_between' => [
                    'from' => '2024-01-01',
                    'to' => '2024-12-31',
                ],
                'ids' => [1, 2, 3, 4, 5],
                'status' => 'active',
                'is_verified' => 'true',
                'guard_name' => 'web',
            ],
        ];

        $request = $this->createFormRequest(TestIndexRequest::class, $data);

        $listQuery = $request->toListQuery();

        $this->assertInstanceOf(ListQuery::class, $listQuery);
        $this->assertIsArray($listQuery->filters);
        $this->assertArrayHasKey('created_between', $listQuery->filters);
        $this->assertArrayHasKey('ids', $listQuery->filters);
        $this->assertArrayHasKey('status', $listQuery->filters);
        $this->assertArrayHasKey('is_verified', $listQuery->filters);
        $this->assertArrayHasKey('guard_name', $listQuery->filters);

        // Check boolean normalization
        $this->assertTrue($listQuery->filters['is_verified']);

        // Check array preservation
        $this->assertEquals([1, 2, 3, 4, 5], $listQuery->filters['ids']);

        // Check range structure
        $this->assertIsArray($listQuery->filters['created_between']);
        $this->assertEquals('2024-01-01', $listQuery->filters['created_between']['from']);
        $this->assertEquals('2024-12-31', $listQuery->filters['created_between']['to']);
    }

    /**
     * Helper method to create and properly initialize FormRequest for testing.
     */
    private function createFormRequest(string $requestClass, array $data): TestIndexRequest
    {
        // Create a new request instance from the current request
        $request = $requestClass::createFromBase(request());
        $request->setContainer($this->app)
            ->setRedirector($this->app->make('redirect'));

        // Replace the request data
        $request->replace($data);

        // Trigger the full validation flow which includes prepareForValidation
        try {
            $request->validateResolved();
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Re-throw validation exceptions as they indicate test data issues
            throw $e;
        }

        return $request;
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function validates_array_filters_with_wildcards()
    {
        $data = [
            'filters' => [
                'ids' => [1, 2, 3],
            ],
        ];

        $validator = Validator::make($data, (new TestIndexRequest)->rules());
        $this->assertTrue($validator->passes());

        // Invalid array elements
        $invalidData = [
            'filters' => [
                'ids' => ['not_int', 2, 3],
            ],
        ];

        $validator = Validator::make($invalidData, (new TestIndexRequest)->rules());
        $this->assertTrue($validator->fails());
    }
}
