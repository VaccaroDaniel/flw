<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Trade items model.
 *
 * @package    block_stash
 * @copyright  2017 Adrian Greeve - adriangreeve.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_stash;
defined('MOODLE_INTERNAL') || die();

use coding_exception;
use core\invalid_persistent_exception;
use invalid_parameter_exception;
use lang_string;
use stdClass;

/**
 * Trade items model class.
 *
 * @package    block_stash
 * @copyright  2017 Adrian Greeve - adriangreeve.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tradeitems {

    const TABLE = 'block_stash_trade_items';

    /** @var int Primary key. */
    private int $id = 0;

    /** @var int The trade this item belongs to. */
    private int $tradeid = 0;

    /** @var int The item. */
    private int $itemid = 0;

    /** @var int|null The quantity involved in the trade. */
    private ?int $quantity = null;

    /** @var bool True = the user gains this item; false = the user loses it. */
    private bool $gainloss = true;

    /** @var int Unix timestamp of record creation. */
    private int $timecreated = 0;

    /** @var int Unix timestamp of last modification. */
    private int $timemodified = 0;

    /** @var array Validation errors from the last validate() call. */
    private array $errors = [];

    /** @var bool Whether the current data has already been validated. */
    private bool $validated = false;

    /**
     * Constructor.
     *
     * @param int|null $id If > 0, load the record with this ID from the DB.
     * @param stdClass|null $record If set, hydrate from this record.
     */
    public function __construct(int|null $id = 0, stdClass $record = null) {
        if ($id > 0) {
            $this->id = $id;
            $this->read();
        }
        if (!empty($record)) {
            $this->from_record($record);
        }
    }

    // -----------------------------------------------------------------------
    // Schema definition
    // -----------------------------------------------------------------------

    /**
     * Returns the property definitions specific to this model (excluding the
     * standard id / timecreated / timemodified fields).
     *
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'tradeid' => [
                'type' => PARAM_INT,
            ],
            'itemid' => [
                'type' => PARAM_INT,
            ],
            'quantity' => [
                'type'    => PARAM_INT,
                'default' => null,
                'null'    => NULL_ALLOWED,
            ],
            'gainloss' => [
                'type'    => PARAM_BOOL,
                'default' => true,
            ],
        ];
    }

    /**
     * Returns the full property definitions including id, timecreated, timemodified.
     *
     * The result is cached per class for the lifetime of the request.
     *
     * @return array
     */
    public static function properties_definition(): array {
        global $CFG;

        static $cachedef = [];
        if (isset($cachedef[static::class])) {
            return $cachedef[static::class];
        }

        $cachedef[static::class] = static::define_properties();
        $def = &$cachedef[static::class];

        $def['id']           = ['default' => 0, 'type' => PARAM_INT];
        $def['timecreated']  = ['default' => 0, 'type' => PARAM_INT];
        $def['timemodified'] = ['default' => 0, 'type' => PARAM_INT];

        foreach ($def as $property => $definition) {
            if (!array_key_exists('null', $definition)) {
                $def[$property]['null'] = NULL_NOT_ALLOWED;
            }
            if ($CFG->debugdeveloper) {
                if (!array_key_exists('type', $definition)) {
                    throw new coding_exception('Missing type for: ' . $property);
                } else if (isset($definition['message']) && !($definition['message'] instanceof lang_string)) {
                    throw new coding_exception('Invalid error message for: ' . $property);
                }
            }
        }

        return $def;
    }

    /**
     * Returns properties that have a matching *format field (PARAM_RAW + PARAM_INT pair).
     *
     * @return array Keys are property names, values are their format property names.
     */
    public static function get_formatted_properties(): array {
        $properties = static::properties_definition();

        $formatted = [];
        foreach ($properties as $property => $definition) {
            $propertyformat = $property . 'format';
            if ($definition['type'] == PARAM_RAW
                    && array_key_exists($propertyformat, $properties)
                    && $properties[$propertyformat]['type'] == PARAM_INT) {
                $formatted[$property] = $propertyformat;
            }
        }

        return $formatted;
    }

    // -----------------------------------------------------------------------
    // Serialisation
    // -----------------------------------------------------------------------

    /**
     * Hydrate this instance from a DB record.
     *
     * Silently ignores unknown fields so partial records (e.g. from JOINs) work.
     *
     * @param stdClass $record
     * @return static
     */
    public function from_record(stdClass $record): static {
        $this->validated = false;
        foreach ((array) $record as $property => $value) {
            switch ($property) {
                case 'id':           $this->id           = (int) $value; break;
                case 'tradeid':      $this->tradeid       = (int) $value; break;
                case 'itemid':       $this->itemid        = (int) $value; break;
                case 'quantity':     $this->quantity      = $value !== null ? (int) $value : null; break;
                case 'gainloss':     $this->gainloss      = (bool) $value; break;
                case 'timecreated':  $this->timecreated   = (int) $value; break;
                case 'timemodified': $this->timemodified  = (int) $value; break;
            }
        }
        return $this;
    }

    /**
     * Produce a DB record from this instance.
     *
     * @return stdClass
     */
    public function to_record(): stdClass {
        $record = new stdClass();
        $record->id           = $this->id;
        $record->tradeid      = $this->tradeid;
        $record->itemid       = $this->itemid;
        $record->quantity     = $this->quantity;
        $record->gainloss     = $this->gainloss;
        $record->timecreated  = $this->timecreated;
        $record->timemodified = $this->timemodified;
        return $record;
    }

    // -----------------------------------------------------------------------
    // Validation
    // -----------------------------------------------------------------------

    /**
     * Validates the data against the property definitions.
     *
     * @return array|true True when valid; an array of property => lang_string errors otherwise.
     */
    public function validate(): array|true {
        if ($this->validated) {
            return empty($this->errors) ? true : $this->errors;
        }

        $errors = [];
        $properties = static::properties_definition();
        $record = (array) $this->to_record();

        foreach ($properties as $property => $definition) {
            $value = $record[$property] ?? null;

            // Required check (no 'default' key means the field is required).
            if ($value === null && !array_key_exists('default', $definition)) {
                $errors[$property] = new lang_string('requiredelement', 'form');
                continue;
            }

            // Type check.
            try {
                // validate_param() rejects false for PARAM_BOOL; cast to 0 first.
                $checkvalue = ($definition['type'] === PARAM_BOOL && $value === false) ? 0 : $value;
                validate_param($checkvalue, $definition['type'], $definition['null']);
            } catch (invalid_parameter_exception $e) {
                $errors[$property] = isset($definition['message'])
                    ? $definition['message']
                    : new lang_string('invaliddata', 'error');
                continue;
            }

            // Choices check.
            if (isset($definition['choices']) && !in_array($value, $definition['choices'])) {
                $errors[$property] = isset($definition['message'])
                    ? $definition['message']
                    : new lang_string('invaliddata', 'error');
                continue;
            }
        }

        $this->validated = true;
        $this->errors    = $errors;

        return empty($errors) ? true : $errors;
    }

    /**
     * Returns true when the current data passes validation.
     *
     * @return bool
     */
    public function is_valid(): bool {
        return $this->validate() === true;
    }

    /**
     * Returns the validation errors from the last validate() call.
     *
     * @return array
     */
    public function get_errors(): array {
        $this->validate();
        return $this->errors;
    }

    // -----------------------------------------------------------------------
    // CRUD
    // -----------------------------------------------------------------------

    /**
     * Load the record from the DB by the current ID.
     *
     * @return static
     */
    public function read(): static {
        global $DB;

        if ($this->id <= 0) {
            throw new coding_exception('id is required to load');
        }
        $record = $DB->get_record(static::TABLE, ['id' => $this->id], '*', MUST_EXIST);
        $this->from_record($record);
        $this->validated = true;

        return $this;
    }

    /**
     * Insert a new record into the DB.
     *
     * @return static
     */
    public function create(): static {
        global $DB;

        if ($this->id) {
            throw new coding_exception('Cannot create an object that has an ID defined.');
        }
        if (!$this->is_valid()) {
            throw new invalid_persistent_exception($this->get_errors());
        }

        $now = time();
        $this->timecreated  = $now;
        $this->timemodified = $now;

        $record = $this->to_record();
        unset($record->id);

        $this->id        = $DB->insert_record(static::TABLE, $record);
        $this->validated = true;

        return $this;
    }

    /**
     * Update the existing DB record.
     *
     * @return bool True on success.
     */
    public function update(): bool {
        global $DB;

        if ($this->id <= 0) {
            throw new coding_exception('id is required to update');
        }
        if (!$this->is_valid()) {
            throw new invalid_persistent_exception($this->get_errors());
        }

        $this->timemodified = time();

        $record = $this->to_record();
        unset($record->timecreated);

        $result          = $DB->update_record(static::TABLE, $record);
        $this->validated = true;

        return $result;
    }

    /**
     * Delete the record from the DB.
     *
     * @return bool True on success.
     */
    public function delete(): bool {
        global $DB;

        if ($this->id <= 0) {
            throw new coding_exception('id is required to delete');
        }

        $result = $DB->delete_records(static::TABLE, ['id' => $this->id]);
        if ($result) {
            $this->id = 0;
        }

        return $result;
    }

    // -----------------------------------------------------------------------
    // Getters / setters
    // -----------------------------------------------------------------------

    /**
     * Get the record ID.
     *
     * @return int
     */
    public function get_id(): int {
        return $this->id;
    }

    /**
     * Get the trade ID.
     *
     * @return int
     */
    public function get_tradeid(): int {
        return $this->tradeid;
    }

    /**
     * Set the trade ID.
     *
     * @param int $tradeid
     * @return static
     */
    public function set_tradeid(int $tradeid): static {
        $this->tradeid    = $tradeid;
        $this->validated  = false;
        return $this;
    }

    /**
     * Get the item ID.
     *
     * @return int
     */
    public function get_itemid(): int {
        return $this->itemid;
    }

    /**
     * Set the item ID.
     *
     * @param int $itemid
     * @return static
     */
    public function set_itemid(int $itemid): static {
        $this->itemid    = $itemid;
        $this->validated = false;
        return $this;
    }

    /**
     * Get the quantity.
     *
     * @return int|null
     */
    public function get_quantity(): ?int {
        return $this->quantity;
    }

    /**
     * Set the quantity.
     *
     * @param int|null $quantity
     * @return static
     */
    public function set_quantity(?int $quantity): static {
        $this->quantity  = $quantity;
        $this->validated = false;
        return $this;
    }

    /**
     * Get the gain/loss direction (true = gain, false = loss).
     *
     * @return bool
     */
    public function get_gainloss(): bool {
        return $this->gainloss;
    }

    /**
     * Set the gain/loss direction.
     *
     * @param bool $gainloss
     * @return static
     */
    public function set_gainloss(bool $gainloss): static {
        $this->gainloss  = $gainloss;
        $this->validated = false;
        return $this;
    }

    // -----------------------------------------------------------------------
    // Static DB helpers
    // -----------------------------------------------------------------------

    /**
     * Load a single record matching the given filters.
     *
     * @param array $filters
     * @return static|false False when no record found.
     */
    public static function get_record(array $filters = []): static|false {
        global $DB;

        $record = $DB->get_record(static::TABLE, $filters);
        return $record ? new static(0, $record) : false;
    }

    /**
     * Load a list of records matching the given filters.
     *
     * @param array  $filters
     * @param string $sort    Field to sort by.
     * @param string $order   Sort direction.
     * @param int    $skip    Offset.
     * @param int    $limit   Maximum rows to return (0 = all).
     * @return static[]
     */
    public static function get_records(array $filters = [], string $sort = '', string $order = 'ASC',
                                       int $skip = 0, int $limit = 0): array {
        global $DB;

        $orderby = '';
        if (!empty($sort)) {
            $orderby = $sort . ' ' . $order;
        }

        $records   = $DB->get_records(static::TABLE, $filters, $orderby, '*', $skip, $limit);
        $instances = [];
        foreach ($records as $record) {
            $instances[] = new static(0, $record);
        }
        return $instances;
    }

    /**
     * Load a list of records using a raw WHERE clause.
     *
     * @param string     $select
     * @param array|null $params
     * @param string     $sort
     * @param string     $fields
     * @param int        $limitfrom
     * @param int        $limitnum
     * @return static[]
     */
    public static function get_records_select(string $select, ?array $params = null, string $sort = '',
                                              string $fields = '*', int $limitfrom = 0, int $limitnum = 0): array {
        global $DB;

        $records   = $DB->get_records_select(static::TABLE, $select, $params, $sort, $fields, $limitfrom, $limitnum);
        $instances = [];
        foreach ($records as $key => $record) {
            $instances[$key] = new static(0, $record);
        }
        return $instances;
    }

    /**
     * Count records matching the given conditions.
     *
     * @param array $conditions
     * @return int
     */
    public static function count_records(array $conditions = []): int {
        global $DB;
        return $DB->count_records(static::TABLE, $conditions);
    }

    /**
     * Check whether a record with the given ID exists.
     *
     * @param int $id
     * @return bool
     */
    public static function record_exists(int $id): bool {
        global $DB;
        return $DB->record_exists(static::TABLE, ['id' => $id]);
    }

    /**
     * Return a SQL fragment listing all columns prefixed for use in a SELECT.
     *
     * Use extract_record() to unpack the result row afterwards.
     *
     * @param string      $alias  Table alias used in the query.
     * @param string|null $prefix Column prefix; defaults to table name with underscores removed + '_'.
     * @return string
     */
    public static function get_sql_fields(string $alias, ?string $prefix = null): string {
        global $CFG;

        if ($prefix === null) {
            $prefix = str_replace('_', '', static::TABLE) . '_';
        }

        // Move 'id' to the front.
        $properties = static::properties_definition();
        $id = $properties['id'];
        unset($properties['id']);
        $properties = ['id' => $id] + $properties;

        $fields = [];
        foreach ($properties as $property => $definition) {
            $as = $prefix . $property;
            $fields[] = $alias . '.' . $property . ' AS ' . $as;

            if ($CFG->debugdeveloper && strlen($as) > 30) {
                throw new coding_exception("The alias '$as' for column '$alias.$property' exceeds 30 characters" .
                    ' and will therefore not work across all supported databases.');
            }
        }

        return implode(', ', $fields);
    }

    /**
     * Extract a record from a prefixed result row (inverse of get_sql_fields()).
     *
     * @param stdClass    $row    The full query result row.
     * @param string|null $prefix Column prefix; defaults to table name with underscores removed + '_'.
     * @return stdClass
     */
    public static function extract_record(stdClass $row, ?string $prefix = null): stdClass {
        if ($prefix === null) {
            $prefix = str_replace('_', '', static::TABLE) . '_';
        }
        $prefixlength = strlen($prefix);

        $data = new stdClass();
        foreach ($row as $property => $value) {
            if (strpos($property, $prefix) === 0) {
                $propertyname          = substr($property, $prefixlength);
                $data->$propertyname   = $value;
            }
        }
        return $data;
    }
}
