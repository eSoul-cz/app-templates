<?php

declare(strict_types=1);

namespace App\Infrastructure\Mcp\Tools\Cms;

use App\Domain\AiConnector\ConnectorScope;
use App\Infrastructure\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use App\Domain\Cms\Blocks\BlockCatalogue;
use App\Domain\Cms\Blocks\BlockField;
use App\Domain\Cms\Blocks\BlockFieldType;

#[Name('list_block_types')]
#[Title('List block types')]
#[Description('Lists the block types a page may hold with their fields: type, required, maximum length, allowed values and hints. Call it before create_page or update_page to compose valid blocks.')]
#[IsReadOnly]
final class ListBlockTypesTool extends CmsTool
{
    protected function requiredScope(): ConnectorScope
    {
        return ConnectorScope::ContentRead;
    }

    protected function execute(Request $request, User $user): Response|ResponseFactory
    {
        $this->authorizeView($user);

        $types = [];

        foreach (app(BlockCatalogue::class)->types as $type) {
            $types[] = [
                'type' => $type->name,
                'fields' => array_map(static fn (BlockField $field): array => array_filter([
                    'name' => $field->name,
                    'type' => $field->type->value,
                    'required' => $field->required,
                    'max_length' => $field->maxLength,
                    'allowed_values' => $field->options === [] ? null : array_map(strval(...), array_keys($field->options)),
                    'default' => $field->default,
                    'hint' => $field->type === BlockFieldType::Image ? 'Path returned by upload_image.' : $field->hint,
                ], static fn (mixed $value): bool => $value !== null), $type->fields),
            ];
        }

        return Response::structured(['block_types' => $types, 'block_shape' => ['type' => 'name of a block type', 'data' => 'object with the fields of that type']]);
    }
}
