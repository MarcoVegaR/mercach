<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeCatalog extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Example:
     *  php artisan make:catalog TipoDocumento \
     *    --fields="code:string:50:unique,name:string:120,active:boolean,sort_order:int:nullable" \
     *    --menu="Catálogos" \
     *    --dry-run
     */
    protected $signature = 'make:catalog
        {Name : StudlyCase name, e.g., TipoDocumento}
        {--fields= : Comma-separated field spec e.g. code:string:50:unique,name:string:120,active:boolean}
        {--menu= : Sidebar group label where to place the menu item}
        {--label= : UI label (singular) to use for menu and permission descriptions (e.g., "Bancos")} 
        {--label-plural= : UI label (plural). If omitted, will pluralize --label}
        {--soft-deletes : Include soft deletes (and unique rules aware of deleted_at)}
        {--uuid-route : Use uuid as route key}
        {--force : Overwrite existing files}
        {--dry-run : Show what will be generated without writing files}
    ';

    /**
     * The console command description.
     */
    protected $description = 'Generate a full CRUD catalog (BE/FE) aligned to the boilerplate conventions.';

    public function handle(): int
    {
        $name = (string) $this->argument('Name');
        $fieldsRaw = (string) ($this->option('fields') ?? '');
        $menuGroup = (string) ($this->option('menu') ?? '');
        $labelSingularOpt = (string) ($this->option('label') ?? '');
        $labelPluralOpt = (string) ($this->option('label-plural') ?? '');
        $withSoftDeletes = (bool) $this->option('soft-deletes');
        $withUuidRoute = (bool) $this->option('uuid-route');
        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');

        // Name transforms
        $model = Str::studly($name);
        $snake = Str::snake($model);
        $snakePlural = Str::plural($snake);
        $table = $snakePlural;
        $kebab = Str::kebab($model);
        $kebabPlural = str_replace('_', '-', $snakePlural);
        $slug = $kebab; // singular slug for URL
        $permPrefix = "catalogs.$kebab";
        $routePrefix = "/catalogs/$slug";
        $modelVar = Str::camel($model);

        // Parse fields
        $fields = $this->parseFields($fieldsRaw);

        // Compute file plan
        $timestamp = date('Y_m_d_His');
        $createFiles = [
            "database/migrations/{$timestamp}_create_{$table}_table.php",
            "app/Models/{$model}.php",
            "app/Contracts/Repositories/{$model}RepositoryInterface.php",
            "app/Repositories/{$model}Repository.php",
            "app/Contracts/Services/{$model}ServiceInterface.php",
            "app/Services/{$model}Service.php",
            "app/Http/Requests/{$model}IndexRequest.php",
            "app/Http/Requests/{$model}StoreRequest.php",
            "app/Http/Requests/{$model}UpdateRequest.php",
            "app/Policies/{$model}Policy.php",
            "app/Http/Controllers/{$model}Controller.php",
            "config/permissions/{$snake}.php",
            "resources/js/pages/catalogs/{$slug}/index.tsx",
            "resources/js/pages/catalogs/{$slug}/columns.tsx",
            "resources/js/pages/catalogs/{$slug}/filters.tsx",
            "resources/js/pages/catalogs/{$slug}/form.tsx",
            "resources/js/pages/catalogs/{$slug}/show.tsx",
        ];

        $modifyFiles = [
            'routes/catalogs.php',
            'resources/js/menu/generated.ts',
            'app/Providers/DomainServiceProvider.php',
        ];

        if ($dryRun) {
            $w = $this->output;
            $w->writeln('');
            $w->writeln('<info>make:catalog (dry-run) — Summary</info>');
            $w->writeln('────────────────────────────────────────────────────');
            $w->writeln("Model:        $model");
            $w->writeln("Table:        $table");
            $w->writeln("Snake:        $snake");
            $w->writeln("SnakePlural:  $snakePlural");
            $w->writeln("Kebab:        $kebab");
            $w->writeln("KebabPlural:  $kebabPlural");
            $w->writeln("Slug:         $slug");
            $w->writeln("PermPrefix:   $permPrefix");
            $w->writeln("RoutePrefix:  $routePrefix");
            $w->writeln('UUID Route:   '.($withUuidRoute ? 'yes' : 'no'));
            $w->writeln('SoftDeletes:  '.($withSoftDeletes ? 'yes' : 'no'));
            if ($menuGroup !== '') {
                $w->writeln("Menu Group:   $menuGroup");
            }
            $w->writeln('');
            $w->writeln('');

            $w->writeln('<info>Fields parsed:</info>');
            if (empty($fields)) {
                $w->writeln('  (none provided)');
            } else {
                foreach ($fields as $f) {
                    $args = empty($f['args']) ? '' : ':'.implode(':', $f['args']);
                    $flags = empty($f['flags']) ? '' : ' ['.implode(',', $f['flags']).']';
                    $w->writeln("  - {$f['name']}: {$f['type']}{$args}{$flags}");
                }
            }
            $w->writeln('');

            $w->writeln('<info>Files to create:</info>');
            foreach ($createFiles as $file) {
                $w->writeln("  + $file");
            }
            $w->writeln('');

            $w->writeln('<info>Files to modify:</info>');
            foreach ($modifyFiles as $file) {
                $w->writeln("  ~ $file");
            }
            $w->writeln('');

            $w->writeln('<comment>Next:</comment> run without --dry-run to generate files.');

            return self::SUCCESS;
        }

        // Non-dry-run: execute generation
        try {
            $plan = $this->buildPlan([
                'model' => $model,
                'snake' => $snake,
                'snakePlural' => $snakePlural,
                'table' => $table,
                'kebab' => $kebab,
                'kebabPlural' => $kebabPlural,
                'slug' => $slug,
                'permPrefix' => $permPrefix,
                'routePrefix' => $routePrefix,
                'routeParam' => $snake, // implicit binding param e.g. tipo_documento
                'withSoftDeletes' => $withSoftDeletes,
                'withUuidRoute' => $withUuidRoute,
                'humanSingular' => $labelSingularOpt !== '' ? $labelSingularOpt : $this->humanize($model, true),
                'humanPlural' => $labelPluralOpt !== '' ? $labelPluralOpt : ($labelSingularOpt !== '' ? Str::plural($labelSingularOpt) : $this->humanize($model, false)),
                'fields' => $fields,
            ]);

            $created = $this->executePlan($plan, $force);

            $this->info('Catalog generated successfully: '.$model);
            foreach ($created as $file) {
                $this->line('  + '.$file);
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Generation failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Basic parser for --fields.
     *
     * @return array<int, array{name:string,type:string,args:array<int,string>,flags:array<int,string>}>
     */
    protected function parseFields(string $fieldsRaw): array
    {
        if (trim($fieldsRaw) === '') {
            return [];
        }

        $specs = array_map('trim', explode(',', $fieldsRaw));
        $result = [];
        foreach ($specs as $spec) {
            $parts = array_map('trim', explode(':', $spec));
            if (count($parts) < 2) {
                // default to string if type missing
                $parts[] = 'string';
            }
            $name = array_shift($parts);
            $type = array_shift($parts);

            // Split remaining into args (numeric/size/params) vs flags (alpha words like unique, nullable)
            $args = [];
            $flags = [];
            foreach ($parts as $p) {
                if ($p === '') {
                    continue;
                }
                // enum(...) keep as an arg token
                if (str_starts_with($type, 'enum')) {
                    $args[] = $p;

                    continue;
                }
                if (preg_match('/^[0-9,]+$/', $p)) {
                    $args[] = $p;
                } else {
                    $flags[] = $p;
                }
            }

            $result[] = [
                'name' => $name,
                'type' => $type,
                'args' => $args,
                'flags' => $flags,
            ];
        }

        return $result;
    }

    /**
     * Build generation plan (paths and stub variables).
     *
     * @param  array<string, mixed>  $ctx
     * @return array<string, mixed>
     */
    private function buildPlan(array $ctx): array
    {
        // Columns, fillable, casts for model & migration
        [$migrationColumns, $modelFillable, $modelCasts] = $this->buildColumns($ctx);

        // Service mapping
        [$rowFields, $exportColumns] = $this->buildServiceMaps($ctx);

        // Validation rules
        [$rulesStore, $rulesUpdate, $uniqueWithoutTrashed] = $this->buildRules($ctx);

        // Normalizations
        $norm = $this->buildNormalizations($ctx);

        // Empty form model
        $emptyModel = $this->buildEmptyModel($ctx);

        $timestamp = date('Y_m_d_His');

        // FE tokens
        [$feModelIface, $feUseFormPairs, $feInputs, $feActiveBlock] = $this->buildFeFormTokens($ctx);
        [$feRowTypeFields, $feColumns, $feDisplayExpr] = $this->buildFeColumnsTokens($ctx);
        $feShowRows = $this->buildFeShowRows($ctx);

        return [
            'paths' => [
                'migration' => base_path('database/migrations/'.$timestamp.'_create_'.$ctx['table'].'_table.php'),
                'model' => base_path('app/Models/'.$ctx['model'].'.php'),
                'repoInterface' => base_path('app/Contracts/Repositories/'.$ctx['model'].'RepositoryInterface.php'),
                'repo' => base_path('app/Repositories/'.$ctx['model'].'Repository.php'),
                'serviceInterface' => base_path('app/Contracts/Services/'.$ctx['model'].'ServiceInterface.php'),
                'service' => base_path('app/Services/'.$ctx['model'].'Service.php'),
                'storeRequest' => base_path('app/Http/Requests/'.$ctx['model'].'StoreRequest.php'),
                'updateRequest' => base_path('app/Http/Requests/'.$ctx['model'].'UpdateRequest.php'),
                'indexRequest' => base_path('app/Http/Requests/'.$ctx['model'].'IndexRequest.php'),
                'policy' => base_path('app/Policies/'.$ctx['model'].'Policy.php'),
                'controller' => base_path('app/Http/Controllers/'.$ctx['model'].'Controller.php'),
                'permissions' => base_path('config/permissions/'.$ctx['snake'].'.php'),
                'feIndex' => base_path('resources/js/pages/catalogs/'.$ctx['slug'].'/index.tsx'),
                'feColumns' => base_path('resources/js/pages/catalogs/'.$ctx['slug'].'/columns.tsx'),
                'feFilters' => base_path('resources/js/pages/catalogs/'.$ctx['slug'].'/filters.tsx'),
                'feForm' => base_path('resources/js/pages/catalogs/'.$ctx['slug'].'/form.tsx'),
                'feShow' => base_path('resources/js/pages/catalogs/'.$ctx['slug'].'/show.tsx'),
            ],
            'stubVars' => [
                'model' => $ctx['model'],
                'table' => $ctx['table'],
                'slug' => $ctx['slug'],
                'kebab' => $ctx['kebab'],
                'permPrefix' => $ctx['permPrefix'],
                'route_param' => $ctx['routeParam'],
                'humanLabelSingular' => $ctx['humanSingular'],
                'humanLabelPlural' => $ctx['humanPlural'],
                'uuidColumn' => $ctx['withUuidRoute'] ? "\n            $"."table->uuid('uuid')->unique();" : '',
                'columns' => $migrationColumns,
                'softDeletes' => $ctx['withSoftDeletes'] ? "\n            $".'table->softDeletes();' : '',
                'fillable' => $modelFillable,
                'casts' => $modelCasts,
                'softDeletesUse' => $ctx['withSoftDeletes'] ? "use Illuminate\\Database\\Eloquent\\SoftDeletes;\n" : '',
                'softDeletesTrait' => $ctx['withSoftDeletes'] ? ', SoftDeletes' : '',
                'routeKeyName' => $ctx['withUuidRoute'] ? "\n    public function getRouteKeyName(): string\n    {\n        return 'uuid';\n    }\n" : '',
                'row_fields' => $rowFields,
                'export_columns' => $exportColumns,
                'rules_hint' => 'Generated from --fields',
                'rules_store' => $rulesStore,
                'rules_update' => $rulesUpdate,
                'unique_without_trashed' => $uniqueWithoutTrashed,
                'normalize_upper_strings_ref' => $norm['upper'],
                'normalize_trim_strings_ref' => $norm['trim'],
                'normalize_ints_ref' => $norm['ints'],
                'normalize_decimals_ref' => $norm['decimals'],
                'normalize_booleans_ref' => $norm['bools'],
                'ensure_uuid_if_needed' => $ctx['withUuidRoute'] ? 'if (! $'."this->has('uuid')) { $"."this->merge(['uuid' => (string) \\Illuminate\\Support\\Str::uuid()]); }" : '',
                'empty_model' => $emptyModel,
                // FE
                'fe_form_model_interface_fields' => $feModelIface,
                'fe_form_use_form_pairs' => $feUseFormPairs,
                'fe_form_inputs' => $feInputs,
                'fe_form_active_block' => $feActiveBlock,
                'fe_columns_row_type_fields' => $feRowTypeFields,
                'fe_columns_columns' => $feColumns,
                'fe_columns_display_expr' => 'String('.$feDisplayExpr.')',
                'fe_show_overview_rows' => $feShowRows,
                'fe_show_display_expr' => 'String('.str_replace('row.', '(item as any).', $feDisplayExpr).')',
            ],
        ];
    }

    /**
     * Execute generation plan.
     *
     * @param  array<string, mixed>  $plan
     * @return array<int, string>
     */
    private function executePlan(array $plan, bool $force): array
    {
        $created = [];

        // Render and write backend stubs
        $map = [
            'migration' => 'migration.stub',
            'model' => 'model.stub',
            'repoInterface' => 'repository-interface.stub',
            'repo' => 'repository.stub',
            'serviceInterface' => 'service-interface.stub',
            'service' => 'service.stub',
            'storeRequest' => 'store-request.stub',
            'updateRequest' => 'update-request.stub',
            'indexRequest' => 'index-request.stub',
            'policy' => 'policy.stub',
            'controller' => 'controller.stub',
            'permissions' => 'permissions.stub',
        ];

        foreach ($map as $key => $stubName) {
            $target = $plan['paths'][$key];
            $content = $this->renderStub($stubName, $plan['stubVars']);
            $this->writeFile($target, $content, $force);
            $created[] = $this->relPath($target);
        }

        // Frontend simple stubs
        $feMap = [
            'feIndex' => 'fe-index.stub',
            'feColumns' => 'fe-columns.stub',
            'feFilters' => 'fe-filters.stub',
            'feForm' => 'fe-form.stub',
            'feShow' => 'fe-show.stub',
        ];
        foreach ($feMap as $key => $stubName) {
            $target = $plan['paths'][$key];
            $content = $this->renderStub($stubName, $plan['stubVars']);
            $this->writeFile($target, $content, $force);
            $created[] = $this->relPath($target);
        }

        // Insert routes block
        $routesBlock = $this->routesBlock($plan['stubVars']);
        $this->insertBetweenMarkers(
            base_path('routes/catalogs.php'),
            '// Marker: BEGIN AUTO-GENERATED CATALOG ROUTES (make:catalog)',
            '// Marker: END AUTO-GENERATED CATALOG ROUTES (make:catalog)',
            $routesBlock
        );

        // Insert menu item (no default icon)
        $menuItem = "{ title: '".$plan['stubVars']['humanLabelPlural']."', url: '/catalogs/".$plan['stubVars']['slug']."', perm: '".$plan['stubVars']['permPrefix'].".view' },";
        $this->insertBetweenMarkers(
            base_path('resources/js/menu/generated.ts'),
            '// Marker: BEGIN AUTO-GENERATED NAV ITEMS (make:catalog)',
            '// Marker: END AUTO-GENERATED NAV ITEMS (make:catalog)',
            $menuItem
        );

        // Bindings
        $this->updateDomainBindings($plan['stubVars']);

        // Register policy mapping for the generated model (prevents 403 on authorize)
        $this->updateAuthPolicies($plan['stubVars']);

        return $created;
    }

    private function humanize(string $name, bool $singular): string
    {
        // Convert StudlyCase to space-separated words, very lightweight approach
        $withSpaces = trim(preg_replace('/([a-z])([A-Z])/', '$1 $2', $name) ?? $name);

        return $singular ? $withSpaces : Str::plural($withSpaces);
    }

    private function humanLabel(string $field): string
    {
        return trim(preg_replace('/[_-]+/', ' ', $field) ?? $field);
    }

    /**
     * @param array{
     *   withUuidRoute?: bool,
     *   fields: array<int, array{name:string,type:string,args:array<int,string>,flags:array<int,string>}>
     * } $ctx
     * @return array{0:string,1:string,2:string}
     */
    private function buildColumns(array $ctx): array
    {
        $cols = [];
        $casts = [];
        $fillable = [];

        if ($ctx['withUuidRoute'] ?? false) {
            $fillable[] = "'uuid'";
        }

        foreach ($ctx['fields'] as $f) {
            $name = $f['name'];
            $type = strtolower($f['type']);
            $flags = $f['flags'];
            $args = $f['args'];
            if ($name === 'active') {
                $name = 'is_active';
            }
            if ($name === 'order') {
                $name = 'sort_order';
            }

            $fillable[] = "'{$name}'";

            $nullable = in_array('nullable', $flags, true);
            $unique = in_array('unique', $flags, true);

            $line = '$'.'table->';
            $cast = null;

            if ($type === 'string' || $type === 'varchar') {
                $len = 255;
                if (! empty($args)) {
                    $lenCandidate = (string) $args[0];
                    if ($lenCandidate !== '') {
                        $len = (int) $lenCandidate;
                    }
                }
                $line .= "string('{$name}', {$len})";
            } elseif (in_array($type, ['int', 'integer'])) {
                $line .= "integer('{$name}')";
                $cast = "'{$name}' => 'integer'";
            } elseif ($type === 'bigint') {
                $line .= "bigInteger('{$name}')";
                $cast = "'{$name}' => 'integer'";
            } elseif ($type === 'decimal') {
                $precision = 10;
                $scale = 2;
                if (! empty($args)) {
                    $argStr = (string) $args[0];
                    $commaPos = strpos($argStr, ',');
                    if ($commaPos === false) {
                        if ($argStr !== '') {
                            $precision = (int) $argStr;
                        }
                    } else {
                        $pStr = substr($argStr, 0, $commaPos);
                        $sStr = substr($argStr, $commaPos + 1);
                        if ($pStr !== '') {
                            $precision = (int) $pStr;
                        }
                        if ($sStr !== '') {
                            $scale = (int) $sStr;
                        }
                    }
                }
                $line .= "decimal('{$name}', {$precision}, {$scale})";
            } elseif ($type === 'boolean' || $type === 'bool') {
                $line .= "boolean('{$name}')";
                $cast = "'{$name}' => 'boolean'";
                if ($name === 'is_active') {
                    $line .= '->default(true)';
                }
            } elseif ($type === 'text') {
                $line .= "text('{$name}')";
            } elseif (str_starts_with($type, 'enum')) {
                $line .= "string('{$name}', 50)";
            } else {
                $line .= "string('{$name}', 255)";
            }

            if ($nullable) {
                $line .= '->nullable()';
            }
            if ($unique) {
                $line .= '->unique()';
            }
            $line .= ';';
            $cols[] = $line;

            if ($cast !== null) {
                $casts[] = $cast;
            }
        }

        return [
            "\n            ".implode("\n            ", $cols),
            implode(",\n        ", $fillable),
            implode(",\n            ", $casts),
        ];
    }

    /**
     * @param array{
     *   withUuidRoute?: bool,
     *   fields: array<int, array{name:string,type:string,args:array<int,string>,flags:array<int,string>}>
     * } $ctx
     * @return array{0:string,1:string}
     */
    private function buildServiceMaps(array $ctx): array
    {
        $row = [
            "'id' => $"."model->getAttribute('id')",
        ];
        if ($ctx['withUuidRoute'] ?? false) {
            $row[] = "'uuid' => $"."model->getAttribute('uuid')";
        }
        foreach ($ctx['fields'] as $f) {
            $name = $f['name'];
            if ($name === 'active') {
                $name = 'is_active';
            }
            if ($name === 'order') {
                $name = 'sort_order';
            }
            if ($name === 'is_active') {
                $row[] = "'is_active' => (bool) ($"."model->getAttribute('is_active') ?? true)";
            } else {
                $row[] = "'{$name}' => $"."model->getAttribute('{$name}')";
            }
        }
        $row[] = "'created_at' => $"."model->getAttribute('created_at')";
        $row[] = "'updated_at' => $"."model->getAttribute('updated_at')";

        // Friendly labels for export headers
        $friendly = [
            'id' => '#',
            'uuid' => 'UUID',
            'code' => 'Código',
            'name' => 'Nombre',
            'swift_bic' => 'SWIFT/BIC',
            'is_active' => 'Estado',
            'sort_order' => 'Orden',
            'guard_name' => 'Guard',
            'created_at' => 'Creado',
            'updated_at' => 'Actualizado',
        ];

        $export = ["'id' => '#'"];
        foreach ($ctx['fields'] as $f) {
            $name = $f['name'];
            if ($name === 'active') {
                $name = 'is_active';
            }
            if ($name === 'order') {
                $name = 'sort_order';
            }
            $label = $friendly[$name] ?? ucfirst($this->humanLabel($name));
            $export[] = "'{$name}' => '{$label}'";
        }
        $export[] = "'created_at' => '".$friendly['created_at']."'";

        return [
            implode(",\n            ", $row),
            implode(",\n            ", $export),
        ];
    }

    /**
     * @param array{
     *   table: string,
     *   withSoftDeletes?: bool,
     *   fields: array<int, array{name:string,type:string,args:array<int,string>,flags:array<int,string>}>
     * } $ctx
     * @return array{0:string,1:string,2:string}
     */
    private function buildRules(array $ctx): array
    {
        $store = [];
        $update = [];
        $uniqueWithoutTrashed = ($ctx['withSoftDeletes'] ?? false) ? '->withoutTrashed()' : '';

        foreach ($ctx['fields'] as $f) {
            $name = $f['name'];
            $type = strtolower($f['type']);
            $flags = $f['flags'];
            $args = $f['args'];
            if ($name === 'active') {
                $name = 'is_active';
            }
            if ($name === 'order') {
                $name = 'sort_order';
            }

            $rulesStore = ["'bail'"];
            $rulesUpdate = ["'bail'"];

            $nullable = in_array('nullable', $flags, true);
            if (! $nullable) {
                $rulesStore[] = "'required'";
                $rulesUpdate[] = "'required'";
            } else {
                $rulesStore[] = "'nullable'";
                $rulesUpdate[] = "'nullable'";
            }

            if ($type === 'string' || $type === 'varchar' || str_starts_with($type, 'enum') || $type === 'text') {
                $rulesStore[] = "'string'";
                $rulesUpdate[] = "'string'";
                if (($type === 'string' || $type === 'varchar') && count($args) > 0) {
                    $len = (int) $args[0];
                    $rulesStore[] = "'max:{$len}'";
                    $rulesUpdate[] = "'max:{$len}'";
                }
                if (in_array('unique', $flags, true)) {
                    $rulesStore[] = "Rule::unique('{$ctx['table']}', '{$name}'){$uniqueWithoutTrashed}";
                    $rulesUpdate[] = "Rule::unique('{$ctx['table']}', '{$name}')->ignore(\$currentId){$uniqueWithoutTrashed}";
                }
                if (str_starts_with($type, 'enum')) {
                    $vals = [];
                    if (! empty($f['args'])) {
                        $raw = $f['args'][0];
                        $vals = array_map('trim', explode(',', (string) $raw));
                    }
                    if (! empty($vals)) {
                        $in = "'".implode("','", $vals)."'";
                        $rulesStore[] = "Rule::in([{$in}])";
                        $rulesUpdate[] = "Rule::in([{$in}])";
                    }
                }
            } elseif (in_array($type, ['int', 'integer', 'bigint'])) {
                $rulesStore[] = "'integer'";
                $rulesUpdate[] = "'integer'";
            } elseif ($type === 'decimal') {
                $rulesStore[] = "'numeric'";
                $rulesUpdate[] = "'numeric'";
            } elseif (in_array($type, ['boolean', 'bool'])) {
                $rulesStore[] = "'boolean'";
                $rulesUpdate[] = "'boolean'";
            } elseif (in_array($type, ['date', 'datetime'])) {
                $rulesStore[] = "'date'";
                $rulesUpdate[] = "'date'";
            } else {
                $rulesStore[] = "'string'";
                $rulesUpdate[] = "'string'";
            }

            $store[] = "'{$name}' => [".implode(',', $rulesStore).']';
            $update[] = "'{$name}' => [".implode(',', $rulesUpdate).']';
        }

        return [
            implode(",\n            ", $store),
            implode(",\n            ", $update),
            $uniqueWithoutTrashed,
        ];
    }

    /**
     * Build normalization code snippets for stubs (upper, trim, ints, decimals, bools).
     *
     * @param array{
     *   fields: array<int, array{name:string,type:string,args:array<int,string>,flags:array<int,string>}>
     * } $ctx
     * @return array{upper:string,trim:string,ints:string,decimals:string,bools:string}
     */
    private function buildNormalizations(array $ctx): array
    {
        $upper = [];
        $trim = [];
        $ints = [];
        $decimals = [];
        $bools = [];

        foreach ($ctx['fields'] as $f) {
            $name = $f['name'];
            $type = strtolower($f['type']);
            if ($name === 'active') {
                $name = 'is_active';
            }
            if ($name === 'order') {
                $name = 'sort_order';
            }

            if ($type === 'string' || $type === 'varchar' || $type === 'text' || str_starts_with($type, 'enum')) {
                $trim[] = "if (isset(\$data['{$name}']) && is_string(\$data['{$name}'])) { \$data['{$name}'] = trim(\$data['{$name}']); }";
                if ($name === 'code') {
                    $upper[] = "if (isset(\$data['code']) && is_string(\$data['code'])) { \$data['code'] = strtoupper(\$data['code']); }";
                }
            }

            if (in_array($type, ['int', 'integer', 'bigint'])) {
                $ints[] = "if (array_key_exists('{$name}', \$data)) { \$data['{$name}'] = is_null(\$data['{$name}']) ? null : (int) \$data['{$name}']; }";
            }

            if ($type === 'decimal') {
                $decimals[] = "if (array_key_exists('{$name}', \$data)) { \$data['{$name}'] = is_null(\$data['{$name}']) ? null : (float) \$data['{$name}']; }";
            }

            if (in_array($type, ['boolean', 'bool'])) {
                $bools[] = "if (array_key_exists('{$name}', \$data)) { \$data['{$name}'] = (bool) \$data['{$name}']; }";
            }
        }

        return [
            'upper' => implode("\n        ", $upper),
            'trim' => implode("\n        ", $trim),
            'ints' => implode("\n        ", $ints),
            'decimals' => implode("\n        ", $decimals),
            'bools' => implode("\n        ", $bools),
        ];
    }

    /**
     * @param array{
     *   fields: array<int, array{name:string,type:string,args:array<int,string>,flags:array<int,string>}>
     * } $ctx
     */
    private function buildEmptyModel(array $ctx): string
    {
        $pairs = [];
        foreach ($ctx['fields'] as $f) {
            $name = $f['name'];
            if ($name === 'active') {
                $name = 'is_active';
            }
            if ($name === 'order') {
                $name = 'sort_order';
            }
            $pairs[] = "'{$name}' => null";
        }

        return implode(",\n            ", $pairs);
    }

    /**
     * Build TS interface fields, useForm initial pairs, and inputs JSX for the FE form.
     *
     * @param  array{fields: array<int, array{name:string,type:string,args:array<int,string>,flags:array<int,string>}>}  $ctx
     * @return array{0:string,1:string,2:string,3:string}
     */
    private function buildFeFormTokens(array $ctx): array
    {
        $iface = [];
        $initPairs = [];
        $inputs = [];
        $hasIsActive = false;

        foreach ($ctx['fields'] as $f) {
            $name = $f['name'];
            $type = strtolower($f['type']);
            if ($name === 'active') {
                $name = 'is_active';
            }
            if ($name === 'order') {
                $name = 'sort_order';
            }

            $tsType = $this->tsTypeFor($type);
            $iface[] = "  {$name}?: {$tsType};";

            if (in_array($type, ['boolean', 'bool'], true)) {
                if ($name === 'is_active') {
                    $initPairs[] = '  is_active: Boolean(initial.is_active ?? true),';
                    $hasIsActive = true;
                    // rendered separately in fe_form_active_block
                } else {
                    $initPairs[] = "  {$name}: Boolean(initial.{$name} ?? false),";
                }

                continue;
            }

            // Initial values
            if ($tsType === 'string | null') {
                $initPairs[] = "  {$name}: initial.{$name} ?? '',";
            } else { // number | null
                $initPairs[] = "  {$name}: initial.{$name} ?? null,";
            }

            // Inputs JSX
            $label = $this->friendlyLabel($name);
            $maxLen = '';
            if (in_array($type, ['string', 'varchar'], true) && ! empty($f['args'])) {
                $len = (int) $f['args'][0];
                if ($len > 0) {
                    $maxLen = "\n                maxLength={".$len.'}';
                }
            }
            $extraClass = ($name === 'code') ? "\n                className=\"font-mono\"" : '';
            $ref = ($name === 'code') ? "\n                ref={firstErrorRef}" : '';
            $autoFocus = ($name === 'code') ? "\n                autoFocus" : '';
            $inputs[] = "            <Field id=\"{$name}\" label=\"{$label}\" error={form.errors.{$name}}>
              <Input
                name=\"{$name}\"{$ref}{$autoFocus}
                value={form.data.{$name}}
                onChange={(e) => form.setData('{$name}', e.target.value)}{$maxLen}{$extraClass}
              />
            </Field>";
        }

        $activeBlock = $hasIsActive ? ("          {mode === 'edit' && (\n            <Field id=\"is_active\" label=\"Estado activo\" error={form.errors.is_active}>\n              <ActiveField\n                checked={!!form.data.is_active}\n                onChange={(v) => form.setData('is_active', v)}\n                canToggle={true}\n                activeLabel=\"Registro activo\"\n                inactiveLabel=\"Registro inactivo\"\n              />\n              <FieldError message={form.errors.is_active} />\n            </Field>\n          )}") : '';

        return [
            implode("\n", $iface),
            implode("\n", $initPairs),
            implode("\n\n", $inputs),
            $activeBlock,
        ];
    }

    /**
     * Build FE columns Row type, columns JSX and display expression.
     *
     * @param  array{fields: array<int, array{name:string,type:string,args:array<int,string>,flags:array<int,string>}>}  $ctx
     * @return array{0:string,1:string,2:string}
     */
    private function buildFeColumnsTokens(array $ctx): array
    {
        $rowFields = [];
        $columns = [];
        $hasName = false;
        $hasCode = false;
        $hasIsActive = false;

        foreach ($ctx['fields'] as $f) {
            $name = $f['name'];
            $type = strtolower($f['type']);
            if ($name === 'active') {
                $name = 'is_active';
            }
            if ($name === 'order') {
                $name = 'sort_order';
            }
            $tsType = $this->tsTypeFor($type);

            if ($name === 'name') {
                $hasName = true;
            }
            if ($name === 'code') {
                $hasCode = true;
            }
            if ($name === 'is_active') {
                $hasIsActive = true;
            }

            // Row type (skip id which is static)
            if ($name !== 'id') {
                $rowFields[] = "  {$name}?: {$tsType};";
            }

            // Columns for non-boolean fields
            if (in_array($type, ['boolean', 'bool'], true)) {
                continue; // handled by special is_active column
            }
            $label = $this->friendlyLabel($name);
            if ($name === 'code') {
                $columns[] = "  {\n    accessorKey: 'code',\n    header: 'Código',\n    enableSorting: true,\n    cell: ({ getValue }) => <span className=\"font-mono text-xs\">{String(getValue() ?? '')}</span>,\n  },";
            } else {
                $columns[] = "  { accessorKey: '{$name}', header: '{$label}', enableSorting: true },";
            }
        }

        // Add boolean is_active column if present
        if ($hasIsActive) {
            $columns[] = "  {\n    accessorKey: 'is_active',\n    header: 'Estado',\n    enableSorting: true,\n    cell: ({ getValue }) => {\n      const active = Boolean(getValue());\n      return (\n        <div className=\"flex items-center gap-2\">\n          <span className={'h-2 w-2 shrink-0 rounded-full ' + (active ? 'bg-emerald-500' : 'bg-red-400')} />\n          <Badge variant={active ? 'default' : 'destructive'} className=\"font-medium\">\n            {active ? 'Activo' : 'Inactivo'}\n          </Badge>\n        </div>\n      );\n    },\n  },";
        }

        // Always include created_at before actions
        $columns[] = "  { accessorKey: 'created_at', header: 'Creado', enableSorting: true },";

        $displayExpr = $hasName ? '(row.name ?? row.code ?? row.id)' : ($hasCode ? '(row.code ?? row.id)' : 'row.id');

        return [
            implode("\n", $rowFields),
            implode("\n", $columns),
            $displayExpr,
        ];
    }

    /**
     * Build FE show overview rows (excludes the Estado row which is static in stub).
     *
     * @param  array{fields: array<int, array{name:string,type:string,args:array<int,string>,flags:array<int,string>}>}  $ctx
     */
    private function buildFeShowRows(array $ctx): string
    {
        $rows = [];
        foreach ($ctx['fields'] as $f) {
            $name = $f['name'];
            $type = strtolower($f['type']);
            if ($name === 'active') {
                $name = 'is_active';
            }
            if ($name === 'order') {
                $name = 'sort_order';
            }
            if ($name === 'is_active') {
                continue;
            } // handled separately

            $label = $this->friendlyLabel($name);
            $mono = ($name === 'code') ? ' font-mono' : '';
            $rows[] = "                <div>\n                  <dt className=\"text-muted-foreground text-sm font-medium\">{$label}</dt>\n                  <dd className=\"mt-1 text-sm{$mono}\">{String((item as any).{$name} ?? '—')}</dd>\n                </div>";
        }

        return implode("\n", $rows);
    }

    private function tsTypeFor(string $type): string
    {
        $type = strtolower($type);
        if (in_array($type, ['int', 'integer', 'bigint', 'decimal'], true)) {
            return 'number | null';
        }
        if (in_array($type, ['boolean', 'bool'], true)) {
            return 'boolean | null';
        }

        return 'string | null';
    }

    private function friendlyLabel(string $name): string
    {
        $map = [
            'code' => 'Código',
            'name' => 'Nombre',
            'is_active' => 'Estado',
            'sort_order' => 'Orden',
            'swift_bic' => 'SWIFT/BIC',
        ];

        return $map[$name] ?? ucfirst($this->humanLabel($name));
    }

    /**
     * Build the routes block to insert between markers.
     *
     * @param array{
     *   model:string, slug:string, permPrefix:string, route_param:string, kebab:string
     * } $v
     */
    private function routesBlock(array $v): string
    {
        $ctrl = '\\App\\Http\\Controllers\\'.$v['model'].'Controller::class';
        $base = '/catalogs/'.$v['slug'];
        $perm = $v['permPrefix'];
        $param = $v['route_param'];
        $kebab = $v['kebab'];

        return "\nRoute::middleware(['auth','verified'])->group(function () {\n".
            "    Route::get('{$base}', [{$ctrl}, 'index'])->middleware('permission:{$perm}.view')->name('catalogs.{$kebab}.index');\n".
            "    Route::get('{$base}/create', [{$ctrl}, 'create'])->middleware('permission:{$perm}.create')->name('catalogs.{$kebab}.create');\n".
            "    Route::post('{$base}', [{$ctrl}, 'store'])->middleware('permission:{$perm}.create')->name('catalogs.{$kebab}.store');\n".
            "    Route::get('{$base}/export', [{$ctrl}, 'export'])->middleware('permission:{$perm}.export')->name('catalogs.{$kebab}.export');\n".
            "    Route::post('{$base}/bulk', [{$ctrl}, 'bulk'])->middleware('permission:{$perm}.delete|{$perm}.restore|{$perm}.forceDelete|{$perm}.setActive')->name('catalogs.{$kebab}.bulk');\n".
            "    Route::get('{$base}/selected', [{$ctrl}, 'selected'])->middleware('permission:{$perm}.view')->name('catalogs.{$kebab}.selected');\n".
            "    Route::get('{$base}/{".$param."}', [{$ctrl}, 'show'])->middleware('permission:{$perm}.view')->name('catalogs.{$kebab}.show');\n".
            "    Route::get('{$base}/{".$param."}/edit', [{$ctrl}, 'edit'])->middleware('permission:{$perm}.update')->name('catalogs.{$kebab}.edit');\n".
            "    Route::put('{$base}/{".$param."}', [{$ctrl}, 'update'])->middleware('permission:{$perm}.update')->name('catalogs.{$kebab}.update');\n".
            "    Route::patch('{$base}/{".$param."}/active', [{$ctrl}, 'setActive'])->middleware('permission:{$perm}.setActive')->name('catalogs.{$kebab}.setActive');\n".
            "    Route::delete('{$base}/{".$param."}', [{$ctrl}, 'destroy'])->middleware('permission:{$perm}.delete')->name('catalogs.{$kebab}.destroy');\n".
            "});\n";
    }

    // ---------------- I/O & Utilities ----------------

    private function stubDir(): string
    {
        return base_path('stubs/catalog');
    }

    /**
     * Render a stub file by replacing {{ tokens }} with values.
     *
     * @param  array<string, string>  $vars
     */
    private function renderStub(string $name, array $vars): string
    {
        $path = $this->stubDir().DIRECTORY_SEPARATOR.$name;
        if (! is_file($path)) {
            throw new \RuntimeException("Stub not found: {$name}");
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new \RuntimeException("Failed to read stub: {$name}");
        }

        foreach ($vars as $k => $v) {
            $contents = str_replace('{{ '.$k.' }}', (string) $v, $contents);
        }

        return $contents;
    }

    private function ensureDirectory(string $targetFile): void
    {
        $dir = dirname($targetFile);
        if (! is_dir($dir)) {
            if (! mkdir($dir, 0775, true) && ! is_dir($dir)) {
                throw new \RuntimeException('Failed to create directory: '.$dir);
            }
        }
    }

    private function writeFile(string $target, string $content, bool $force): void
    {
        $this->ensureDirectory($target);
        if (is_file($target) && ! $force) {
            throw new \RuntimeException('File already exists: '.$this->relPath($target).' (use --force to overwrite)');
        }
        $ok = file_put_contents($target, rtrim($content)."\n");
        if ($ok === false) {
            throw new \RuntimeException('Failed to write file: '.$this->relPath($target));
        }
    }

    private function relPath(string $path): string
    {
        $base = rtrim(base_path(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        return str_starts_with($path, $base) ? substr($path, strlen($base)) : $path;
    }

    /**
     * Insert a block between begin/end markers idempotently.
     */
    private function insertBetweenMarkers(string $file, string $begin, string $end, string $block): void
    {
        if (! is_file($file)) {
            throw new \RuntimeException('Target file not found: '.$this->relPath($file));
        }
        $contents = file_get_contents($file);
        if ($contents === false) {
            throw new \RuntimeException('Failed to read: '.$this->relPath($file));
        }

        $start = strpos($contents, $begin);
        $stop = strpos($contents, $end);
        if ($start === false || $stop === false || $stop <= $start) {
            throw new \RuntimeException('Markers not found or in wrong order in '.$this->relPath($file));
        }

        $existing = substr($contents, $start + strlen($begin), $stop - ($start + strlen($begin)));
        if (str_contains($existing, trim($block))) {
            // Already present, nothing to do
            return;
        }

        // Ensure proper newlines and indentation
        $insertion = rtrim($existing)."\n".rtrim($block)."\n";
        $new = substr($contents, 0, $start + strlen($begin))
            .$insertion
            .substr($contents, $stop);

        $ok = file_put_contents($file, $new);
        if ($ok === false) {
            throw new \RuntimeException('Failed to update file: '.$this->relPath($file));
        }
    }

    /**
     * Add repository/service bindings to DomainServiceProvider idempotently.
     *
     * @param  array{model:string}  $v
     */
    private function updateDomainBindings(array $v): void
    {
        $file = base_path('app/Providers/DomainServiceProvider.php');
        $contents = file_get_contents($file);
        if ($contents === false) {
            throw new \RuntimeException('Failed to read DomainServiceProvider.php');
        }

        $model = $v['model'];
        $repoIfaceStr = "\\App\\Contracts\\Repositories\\{$model}RepositoryInterface::class";
        $svcIfaceStr = "\\App\\Contracts\\Services\\{$model}ServiceInterface::class";

        // Insert repository binding before services docblock
        if (! str_contains($contents, $repoIfaceStr)) {
            $repoBind = "\n        $"."this->app->bind(\n            \\App\\Contracts\\Repositories\\{$model}RepositoryInterface::class,\n            \\App\\Repositories\\{$model}Repository::class\n        );\n";
            $marker = "\n    }\n\n    /**\n     * Registra bindings de servicios";
            $pos = strpos($contents, $marker);
            if ($pos === false) {
                throw new \RuntimeException('Could not locate insertion point for repositories');
            }
            $contents = substr($contents, 0, $pos).$repoBind.substr($contents, $pos);
        }

        // Insert service interface + concrete binding before exporters
        if (! str_contains($contents, $svcIfaceStr)) {
            $svcBind = "\n        $"."this->app->bind(\n            \\App\\Contracts\\Services\\{$model}ServiceInterface::class,\n            \\App\\Services\\{$model}Service::class\n        );\n\n        $"."this->app->bind(\\App\\Services\\{$model}Service::class, function (\\Illuminate\\Contracts\\Container\\Container $"."app) {\n            return new \\App\\Services\\{$model}Service(\n                $"."app->make(\\App\\Contracts\\Repositories\\{$model}RepositoryInterface::class),\n                $"."app\n            );\n        });\n";
            $expMarker = "\n        $"."this->app->bind('exporter.csv'";
            $pos = strpos($contents, $expMarker);
            if ($pos === false) {
                throw new \RuntimeException('Could not locate insertion point for services');
            }
            $contents = substr($contents, 0, $pos).$svcBind.substr($contents, $pos);
        }

        $ok = file_put_contents($file, $contents);
        if ($ok === false) {
            throw new \RuntimeException('Failed to update DomainServiceProvider.php');
        }
    }

    /**
     * Ensure AuthServiceProvider registers the generated Policy mapping.
     * Avoids 403 errors when calling $this->authorize() on the generated resources.
     *
     * @param  array{model:string}  $vars
     */
    protected function updateAuthPolicies(array $vars): void
    {
        $model = $vars['model'];

        $file = base_path('app/Providers/AuthServiceProvider.php');
        if (! is_file($file)) {
            return; // nothing to do if provider doesn't exist
        }

        $contents = file_get_contents($file);
        if ($contents === false) {
            throw new \RuntimeException('Failed to read AuthServiceProvider.php');
        }

        $mapNeedle = "\\App\\Models\\{$model}::class => \\App\\Policies\\{$model}Policy::class";
        if (str_contains($contents, $mapNeedle)) {
            return; // already mapped
        }

        // Try to insert into protected $policies = [ ... ];
        $mapLine = "\n        \\App\\Models\\{$model}::class => \\App\\Policies\\{$model}Policy::class,";
        $updated = preg_replace(
            '/(protected\s+\$policies\s*=\s*\[)(.*?)(\n\s*\];)/s',
            '$1$2'.$mapLine.'$3',
            $contents,
            1,
            $count
        );

        if ($updated === null) {
            throw new \RuntimeException('Regex failed while updating AuthServiceProvider policies');
        }

        if ($count > 0) {
            // Inserted into $policies array
            $ok = file_put_contents($file, $updated);
            if ($ok === false) {
                throw new \RuntimeException('Failed to update AuthServiceProvider.php');
            }

            return;
        }

        // Fallback: insert a Gate::policy call after registerPolicies() in boot()
        $policyCall = "\n        Gate::policy(\\App\\Models\\{$model}::class, \\App\\Policies\\{$model}Policy::class);\n";
        $updated2 = preg_replace('/(\$this->registerPolicies\(\);)/', '$1'.$policyCall, $contents, 1, $count2);
        if ($updated2 !== null && $count2 > 0) {
            $ok = file_put_contents($file, $updated2);
            if ($ok === false) {
                throw new \RuntimeException('Failed to update AuthServiceProvider.php');
            }

            return;
        }

        // As a last resort, append before class closing bracket
        $updated3 = preg_replace('/}\s*$/', $policyCall."}\n", $contents, 1, $count3);
        if ($updated3 !== null && $count3 > 0) {
            $ok = file_put_contents($file, $updated3);
            if ($ok === false) {
                throw new \RuntimeException('Failed to update AuthServiceProvider.php');
            }
        }
    }

    /**
     * Ensure the generated menu file imports a Lucide icon symbol.
     * If an import from 'lucide-react' exists, append the symbol; otherwise, add a new import.
     */
    protected function ensureMenuIconImport(string $icon): void
    {
        $file = base_path('resources/js/menu/generated.ts');
        if (! is_file($file)) {
            return; // menu file may not exist in some setups
        }
        $contents = file_get_contents($file);
        if ($contents === false) {
            throw new \RuntimeException('Failed to read menu file: '.$this->relPath($file));
        }

        // If already present, nothing to do
        if (preg_match('/import\\s*\\{[^}]*\\b'.preg_quote($icon, '/')."\\b[^}]*}\\s*from\\s*['\"]lucide-react['\"];?/", $contents)) {
            return;
        }

        // Try to locate an existing lucide-react import to extend
        if (preg_match("/import\\s*\\{([^}]*)}\\s*from\\s*['\"]lucide-react['\"];?/", $contents, $m, PREG_OFFSET_CAPTURE)) {
            $fullImport = $m[0][0];
            $importStart = $m[0][1];
            $importEnd = $importStart + strlen($fullImport);
            $symbols = trim($m[1][0]);
            $symbolsList = array_map('trim', array_filter(explode(',', $symbols)));
            if (! in_array($icon, $symbolsList, true)) {
                $symbolsList[] = $icon;
                $newImport = 'import { '.implode(', ', $symbolsList).' } from \'lucide-react\';';
                $contents = substr($contents, 0, $importStart).$newImport.substr($contents, $importEnd);
            }
        } else {
            // Insert a new import after the first import line
            $lines = preg_split("/\r?\n/", $contents);
            if ($lines === false) {
                $lines = [$contents];
            }
            // Find first non-empty line (usually the NavItem type import) and insert after it
            $insertionIndex = 0;
            while ($insertionIndex < count($lines) && trim((string) $lines[$insertionIndex]) === '') {
                $insertionIndex++;
            }
            array_splice($lines, $insertionIndex + 1, 0, "import { $icon } from 'lucide-react';");
            $contents = implode("\n", $lines);
        }

        $ok = file_put_contents($file, $contents);
        if ($ok === false) {
            throw new \RuntimeException('Failed to update menu file for icon import');
        }
    }
}
