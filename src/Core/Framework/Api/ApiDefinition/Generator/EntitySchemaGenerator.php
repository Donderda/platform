<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\ApiDefinition\Generator;

use Shopware\Core\Framework\Api\ApiDefinition\ApiDefinitionGeneratorInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AttributesField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BlacklistRuleField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BlobField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CalculatedPriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CartPriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildCountField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Flag;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextWithHtmlField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ObjectField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentFkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PasswordField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceDefinitionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceRulesJsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\SearchKeywordAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TreeLevelField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TreePathField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionDataPayloadField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\WhitelistRuleField;

class EntitySchemaGenerator implements ApiDefinitionGeneratorInterface
{
    public const FORMAT = 'entity-schema';

    /**
     * @var DefinitionRegistry
     */
    private $registry;

    public function __construct(DefinitionRegistry $registry)
    {
        $this->registry = $registry;
    }

    public function supports(string $format): bool
    {
        return $format === self::FORMAT;
    }

    public function generate(): array
    {
        return $this->getSchema();
    }

    public function getSchema(): array
    {
        $schema = [];

        $definitions = $this->registry->getDefinitions();
        ksort($definitions);

        /** @var string|EntityDefinition $definition */
        foreach ($definitions as $definition) {
            if (preg_match('/_translation$/', $definition::getEntityName())) {
                continue;
            }

            $entity = $definition::getEntityName();

            $schema[$entity] = $this->getEntitySchema($definition);
        }

        return $schema;
    }

    private function getEntitySchema(string $definition): array
    {
        $fields = $definition::getFields();

        $properties = [];
        foreach ($fields as $field) {
            $properties[$field->getPropertyName()] = $this->parseField($definition, $field);
        }

        return [
            'entity' => $definition::getEntityName(),
            'properties' => $properties,
        ];
    }

    private function parseField(string $definition, Field $field): array
    {
        $flags = [];
        /** @var Flag $flag */
        foreach ($field->getFlags() as $flag) {
            $flags = array_replace_recursive($flags, iterator_to_array($flag->parse()));
        }

        switch (true) {
            case $field instanceof TranslatedField:
                $property = $this->parseField(
                    $definition,
                    EntityDefinitionQueryHelper::getTranslatedField($definition, $field)
                );
                $property['flags']['translatable'] = true;

                return $property;

            // fields with uuid
            case $field instanceof VersionField:
            case $field instanceof ReferenceVersionField:
            case $field instanceof ParentFkField:
            case $field instanceof FkField:
            case $field instanceof IdField:
                return ['type' => 'uuid', 'flags' => $flags];

            // json fields
            case $field instanceof ListField:
                return ['type' => 'json_list', 'flags' => $flags];

            case $field instanceof AttributesField:
            case $field instanceof VersionDataPayloadField:
            case $field instanceof WhitelistRuleField:
            case $field instanceof BlacklistRuleField:
            case $field instanceof CalculatedPriceField:
            case $field instanceof CartPriceField:
            case $field instanceof PriceDefinitionField:
            case $field instanceof PriceField:
            case $field instanceof PriceRulesJsonField:
            case $field instanceof ObjectField:
            case $field instanceof JsonField:
                $nested = [];
                if ($field instanceof JsonField) {
                    foreach ($field->getPropertyMapping() as $nestedField) {
                        $nested[$nestedField->getPropertyName()] = $this->parseField($definition, $nestedField);
                    }
                }

                return [
                    'type' => 'json_object',
                    'properties' => $nested,
                    'flags' => $flags,
                ];

            // association fields
            case $field instanceof SearchKeywordAssociationField:
            case $field instanceof OneToManyAssociationField:
            case $field instanceof ChildrenAssociationField:
            case $field instanceof TranslationsAssociationField:
                /** @var AssociationInterface $field */
                if (!$field instanceof AssociationInterface) {
                    throw new \RuntimeException('Field should implement AssociationInterface');
                }

                return [
                    'type' => 'association',
                    'relation' => 'one_to_many',
                    'entity' => $field->getReferenceClass()::getEntityName(),
                    'flags' => $flags,
                ];

            case $field instanceof ParentAssociationField:
            case $field instanceof ManyToOneAssociationField:
                /** @var AssociationInterface $field */
                if (!$field instanceof AssociationInterface) {
                    throw new \RuntimeException('Field should implement AssociationInterface');
                }

                return [
                    'type' => 'association',
                    'relation' => 'many_to_one',
                    'entity' => $field->getReferenceClass()::getEntityName(),
                    'flags' => $flags,
                ];

            case $field instanceof ManyToManyAssociationField:
                /* @var AssociationInterface $field */
                return [
                    'type' => 'association',
                    'relation' => 'many_to_many',
                    'entity' => $field->getReferenceDefinition()::getEntityName(),
                    'flags' => $flags,
                ];

            case $field instanceof OneToOneAssociationField:
                return [
                    'type' => 'association',
                    'relation' => 'one_to_one',
                    'entity' => $field->getReferenceClass()::getEntityName(),
                    'flags' => $flags,
                ];

            // int fields
            case $field instanceof ChildCountField:
            case $field instanceof TreeLevelField:
            case $field instanceof IntField:
                return ['type' => 'int', 'flags' => $flags];

            // long text fields
            case $field instanceof TreePathField:
            case $field instanceof LongTextField:
            case $field instanceof LongTextWithHtmlField:
                return ['type' => 'text', 'flags' => $flags];

            // date fields
            case $field instanceof UpdatedAtField:
            case $field instanceof CreatedAtField:
            case $field instanceof DateField:
                return ['type' => 'date', 'flags' => $flags];

            // scalar fields
            case $field instanceof PasswordField:
                return ['type' => 'password', 'flags' => $flags];

            case $field instanceof FloatField:
                return ['type' => 'float', 'flags' => $flags];

            case $field instanceof StringField:
                return ['type' => 'string', 'flags' => $flags];

            case $field instanceof BlobField:
                return ['type' => 'blob', 'flags' => $flags];

            case $field instanceof BoolField:
                return ['type' => 'boolean', 'flags' => $flags];

            default:
                return ['type' => get_class($field), 'flags' => $flags];
        }
    }
}