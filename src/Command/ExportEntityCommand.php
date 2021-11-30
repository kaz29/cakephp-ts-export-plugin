<?php
declare(strict_types=1);

namespace TsExport\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Database\Schema\CollectionInterface;
use Cake\Database\Schema\TableSchemaInterface;
use Cake\Datasource\ConnectionManager;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Utility\Inflector;

/**
 * ExportEntity command.
 */
class ExportEntityCommand extends Command
{
    private $ignores = [
        'phinxlog',
        'sessions',
    ];

    /**
     * Hook method for defining this command's option parser.
     *
     * @see https://book.cakephp.org/4/en/console-commands/commands.html#defining-arguments-and-options
     * @param \Cake\Console\ConsoleOptionParser $parser The parser to be defined
     * @return \Cake\Console\ConsoleOptionParser The built parser.
     */
    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser = parent::buildOptionParser($parser);

        $parser
            ->setDescription('Export cakephp entity to typescript type definition.')
            ->addArgument('table', [
                'help' => 'Table Name (ex. Users).',
                'required' => false,
            ])
            ->addOption('all', [
                'help' => 'Export all entities',
                'boolean' => true,
            ])
            ->addOption('suffix', [
                'help' => 'Interface suffix',
                'required' => false,
                'boolean' => false,
            ]);

        return $parser;
    }

    /**
     * Implement this method with your command's logic.
     *
     * @param \Cake\Console\Arguments $args The command arguments.
     * @param \Cake\Console\ConsoleIo $io The console io
     * @return null|void|int The exit code or null for success
     */
    public function execute(Arguments $args, ConsoleIo $io)
    {
        if (!$args->getArgument('table') && !$args->getOption('all')) {
            $io->error("Argument 'table' is required when not add '--all' option.");
            $this->abort();
        }

        $db = ConnectionManager::get('default', false);
        $schemaCollection = $db->getSchemaCollection();

        $tableNames = [];
        if ($args->getOption('all')) {
            $tableNames = $this->getTableNames($schemaCollection);
        } else {
            $tableNames[] = $args->getArgument('table');
        }

        $suffix = $args->getOption('suffix');
        foreach ($tableNames as $tableName) {
            $table = TableRegistry::getTableLocator()->get($tableName);
            if (get_class($table) === Table::class) {
                $io->error("{$tableName} not found!");
                $this->abort();
            }

            $schema = $schemaCollection->describe($table->getTable());

            $this->exportTypescriptType($table, $schema, $suffix, $io);
        }
    }

    protected function getTableNames(CollectionInterface $schemaCollection): array
    {
        $tableNames = [];

        $allTableNames = $schemaCollection->listTables();
        $subjectTableNames = array_diff($allTableNames, $this->ignores);
        foreach ($subjectTableNames as $tableName) {
            $camelTableName = lcfirst(Inflector::camelize($tableName));
            $tableNames[] = $camelTableName;
        }

        return $tableNames;
    }

    protected function exportTypescriptType(Table $table, TableSchemaInterface $schema, string|null $suffix, ConsoleIo $io): void
    {
        $entityInfo = explode('\\', $table->getEntityClass());
        $io->out("/**\n * $entityInfo[3] entity interface\n */");
        $io->out('export interface ' . "$entityInfo[3]{$suffix}" . ' {');
            foreach ($schema->columns() as $name) {
            $io->out(str_repeat(' ', 2) . $this->makeTypeScriptColumn(
                $name,
                $schema->getColumnType($name),
                $schema->getColumn($name),
                $schema->isNullable($name)
            ));
        }
        $io->out('}');
    }

    protected function makeTypeScriptColumn(string $name, string $type, array $column, bool $isNullable): string
    {
        switch ($type) {
            case TableSchemaInterface::TYPE_CHAR:
            case TableSchemaInterface::TYPE_STRING:
            case TableSchemaInterface::TYPE_TEXT:
            case TableSchemaInterface::TYPE_UUID:
            case TableSchemaInterface::TYPE_DATE:
            case TableSchemaInterface::TYPE_DATETIME:
            case TableSchemaInterface::TYPE_TIME:
            case TableSchemaInterface::TYPE_TIMESTAMP:
            case TableSchemaInterface::TYPE_DECIMAL:
                $type = 'string';
                break;
            case TableSchemaInterface::TYPE_INTEGER:
            case TableSchemaInterface::TYPE_TINYINTEGER:
            case TableSchemaInterface::TYPE_SMALLINTEGER:
            case TableSchemaInterface::TYPE_FLOAT:
            case TableSchemaInterface::TYPE_BIGINTEGER:
                $type = 'number';
                break;
            case TableSchemaInterface::TYPE_JSON:
                $type = 'string';
                break;
            case TableSchemaInterface::TYPE_BOOLEAN:
                $type = 'boolean';
                break;
            default:
                $type = 'string';
                break;
        }

        $fieldName = lcfirst(Inflector::camelize($name));
        $comment = '';
        if (!empty($column['comment'])) {
            $comment = ' // ' . str_replace("\n", ' ', $column['comment']);
        }

        $optionalMark = $isNullable ? '?' : '';

        return "{$fieldName}{$optionalMark}: {$type}{$comment}";
    }
}
