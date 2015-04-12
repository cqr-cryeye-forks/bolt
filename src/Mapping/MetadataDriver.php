<?php

namespace Bolt\Mapping;

use Bolt\Database\IntegrityChecker;
use Doctrine\Common\Persistence\Mapping\Driver\MappingDriver;
use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Bolt\Mapping\ClassMetadata as BoltClassMetadata;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;

/**
 * This is a Bolt specific metadata driver that provides mapping information
 * for the internal and user-defined schemas. To do this it takes in the constructor,
 * an instance of IntegrityChecker and uses this to read in the schema.
 *
 * @author Ross Riley <riley.ross@gmail.com>
 */
class MetadataDriver implements MappingDriver
{
    
    /**
     * IntegrityChecker object
     */
    protected $integrityChecker;
    
    /**
     * Array of contenttypes
     */
    protected $contenttypes;
    
    /**
     * array of metadata mappings
     */
    protected $metadata;
    
    /**
     * @var array
     */
    protected $defaultAliases = array(
        'bolt_authtoken' => 'Bolt\Entity\Authtoken',
        'bolt_cron' => 'Bolt\Entity\Cron',
        'bolt_log' => 'Bolt\Entity\Log',
        'bolt_log_change' => 'Bolt\Entity\LogChange',
        'bolt_log_system' => 'Bolt\Entity\LogSystem',
        'bolt_relations' => 'Bolt\Entity\Relations',
        'bolt_taxonomy' => 'Bolt\Entity\Taxonomy',
        'bolt_users' => 'Bolt\Entity\Users'
    );
    
    protected $typemap;
    
    /**
     *  Keeps a reference of which metadata is not mapped to
     *  a specific entity.
     * 
     *  @var array $unmapped 
     */
    protected $unmapped;
    
    /**
     * @var string - a default entity for any table not matched
     */
    protected $fallbackEntity = 'Bolt\Entity\Content';
    
    /**
     * @var boolean
     */
    protected $initialized = false;

    /**
     * @param IntegrityChecker $integrityChecker
     */
    public function __construct(IntegrityChecker $integrityChecker, array $contenttypes, array $typemap)
    {
        $this->integrityChecker = $integrityChecker;
        $this->contenttypes = $contenttypes;
        $this->typemap = $typemap;
    }
    
    /**
     * Reads the schema from IntegrityChecker and creates mapping data
     * 
     * @return void
     */
    public function initialize()
    {
        foreach ($this->integrityChecker->getTablesSchema() as $table) {
            $this->loadMetadataForTable($table);
        }
        $this->initialized = true;
    }
    
    protected function loadMetadataForTable(Table $table)
    {
        $tblName = $table->getName();
        
        if (isset($this->defaultAliases[$tblName])) {
            $className = $this->defaultAliases[$tblName];
        } else {
            $className = $tblName;
            $this->unmapped[] = $tblName;
        }
        
        $this->metadata[$className] = array();
        $this->metadata[$className]['identifier'] = $table->getPrimaryKey();
        $this->metadata[$className]['table'] = $table->getName();
        foreach ($table->getColumns() as $colName=>$column) {
            
            $mapping['fieldname'] = $colName;
            $mapping['type'] = $column->getType()->getName();
            $mapping['fieldtype'] = $this->getFieldTypeFor($table->getName(), $column);
            $mapping['length'] = $column->getLength();
            $mapping['nullable'] = $column->getNotnull();
            $mapping['platformOptions'] = $column->getPlatformOptions();
            $mapping['precision'] = $column->getPrecision();
            $mapping['scale'] = $column->getScale();
            $mapping['default'] = $column->getDefault();
            $mapping['columnDefinition'] = $column->getColumnDefinition();
            $mapping['autoincrement'] = $column->getAutoincrement();
            
            $this->metadata[$className]['fields'][$colName] = $mapping;
        }
    }

    /**
     * @param string $className Fully Qualified name
     * @param ClassMetadata $metadata instance of metadata class to load with data
     */
    public function loadMetadataForClass($className, ClassMetadata $metadata = null)
    {
        if (null === $metadata) {
            $metadata = new BoltClassMetadata($className);
        }
        if (!$this->initialized) {
            $this->initialize();
        }
        if (array_key_exists($className, $this->metadata)) {
            $data = $this->metadata[$className];
            $metadata->setTableName($data['table']);
            $metadata->setIdentifier($data['identifier']);
            $metadata->setFieldMappings($data['fields']);
            return $metadata;
            
        } else {
            throw new \Exception("Attempted to load mapping data for unmapped class $className");
        }
        
    }
    
    /**
     * undocumented function
     *
     * @return void
     */
    protected function getFieldTypeFor($name, $column)
    {
        $contentKey = $this->integrityChecker->getKeyForTable($name);
        if ($contentKey && isset($this->contenttypes[$contentKey][$column->getName()])) {
            $type = $this->contenttypes[$contentKey]['fields'][$column->getName()]['type'];
        } elseif ($column->getType()) {
            $type = get_class($column->getType());
        } 
                
        if (isset($this->typemap[$type])) {
            $type = new $this->typemap[$type];
        } else {
            $type = new $this->typemap['text'];
        }
        
        return $type;
    }

    /**
     * Gets the names of all mapped classes known to this driver.
     *
     * @return array The names of all mapped classes known to this driver.
     */
    public function getAllClassNames()
    {
        return array_keys($this->metadata);
    }
    
    /**
     * Gets a list of tables that are not mapped to specific entities.
     *
     * @return array
     */
    public function getUnmapped()
    {
        return $this->unmapped;
    }
    
    /**
     * Adds an alias mapping from an internal name to a Fully Qualified Entity.
     *
     * @param string $alias.
     * @param string $entity.
     *
     * @return void.
     */
    public function setDefaultAlias($alias, $entity)
    {
        $this->defaultAliases[$alias] = $entity;
    }
    
    /**
     * Returns the metadata for a given class name.
     * @param string $className
     *
     * @return The class metadata.
     */
    public function getClassMetadata($className)
    {
        if (!$this->initialized) {
            $this->initialize();
        }
        if (array_key_exists($className, $this->metadata)) {
            return $this->metadata[$className];
        }
        
        return false;
    }

    /**
     * Not implemented, always returns false.
     *
     * @param string $className
     *
     * @return boolean
     */
    public function isTransient($className)
    {
        return false;
    }
}
