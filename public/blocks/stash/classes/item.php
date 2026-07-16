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
 * Item model.
 *
 * @package    block_stash
 * @copyright  2016 Frédéric Massart - FMCorz.net
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
 * Item model class.
 *
 * @package    block_stash
 * @copyright  2016 Frédéric Massart - FMCorz.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class item {

    const TABLE = 'block_stash_items';

    /** @var int Primary key. */
    private int $id = 0;

    /** @var int The stash this item belongs to. */
    private int $stashid = 0;

    /** @var string The display name of the item. */
    private string $name = '';

    /** @var int|null Maximum number a user may hold; null means unlimited. */
    private ?int $maxnumber = null;

    /** @var string|null Rich text description of the item. */
    private ?string $detail = null;

    /** @var int|null Format of the detail field (FORMAT_* constant). */
    private ?int $detailformat = null;

    /** @var int|null Total stock limit; null means unrestricted. */
    private ?int $amountlimit = null;

    /** @var int|null Current stock remaining; null when unrestricted. */
    private ?int $currentamount = null;

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
            'stashid' => [
                'type' => PARAM_INT,
            ],
            'name' => [
                'type' => PARAM_TEXT,
            ],
            'maxnumber' => [
                'type'    => PARAM_INT,
                'default' => null,
                'null'    => NULL_ALLOWED,
            ],
            'detail' => [
                'type'    => PARAM_RAW,
                'default' => null,
                'null'    => NULL_ALLOWED,
            ],
            'detailformat' => [
                'type'    => PARAM_INT,
                'default' => null,
                'null'    => NULL_ALLOWED,
            ],
            'amountlimit' => [
                'type'    => PARAM_INT,
                'default' => null,
                'null'    => NULL_ALLOWED,
            ],
            'currentamount' => [
                'type'    => PARAM_INT,
                'default' => null,
                'null'    => NULL_ALLOWED,
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
     * For item, this returns ['detail' => 'detailformat'].
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
                case 'id':            $this->id            = (int) $value; break;
                case 'stashid':       $this->stashid       = (int) $value; break;
                case 'name':          $this->name          = (string) $value; break;
                case 'maxnumber':     $this->maxnumber     = $value !== null ? (int) $value : null; break;
                case 'detail':        $this->detail        = $value !== null ? (string) $value : null; break;
                case 'detailformat':  $this->detailformat  = $value !== null ? (int) $value : null; break;
                case 'amountlimit':   $this->amountlimit   = $value !== null ? (int) $value : null; break;
                case 'currentamount': $this->currentamount = $value !== null ? (int) $value : null; break;
                case 'timecreated':   $this->timecreated   = (int) $value; break;
                case 'timemodified':  $this->timemodified  = (int) $value; break;
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
        $record->id            = $this->id;
        $record->stashid       = $this->stashid;
        $record->name          = $this->name;
        $record->maxnumber     = $this->maxnumber;
        $record->detail        = $this->detail;
        $record->detailformat  = $this->detailformat;
        $record->amountlimit   = $this->amountlimit;
        $record->currentamount = $this->currentamount;
        $record->timecreated   = $this->timecreated;
        $record->timemodified  = $this->timemodified;
        return $record;
    }

    // -----------------------------------------------------------------------
    // Validation
    // -----------------------------------------------------------------------

    /**
     * Validates the data against the property definitions, including custom
     * validate_*() hook methods.
     *
     * @return array|true True when valid; an array of property => lang_string errors otherwise.
     */
    public function validate(): array|true {
        global $CFG;

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

            // Custom validate_*() hook.
            $method = 'validate_' . $property;
            if (method_exists($this, $method)) {
                if ($CFG->debugdeveloper) {
                    $reflection = new \ReflectionMethod($this, $method);
                    if (!$reflection->isProtected()) {
                        throw new coding_exception('The method ' . get_class($this) . '::' . $method . ' should be protected.');
                    }
                }
                $valid = $this->{$method}($value);
                if ($valid !== true) {
                    if (!($valid instanceof lang_string)) {
                        throw new coding_exception('Unexpected error message.');
                    }
                    $errors[$property] = $valid;
                    continue;
                }
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
     * Get the stash ID.
     *
     * @return int
     */
    public function get_stashid(): int {
        return $this->stashid;
    }

    /**
     * Set the stash ID.
     *
     * @param int $stashid
     * @return static
     */
    public function set_stashid(int $stashid): static {
        $this->stashid   = $stashid;
        $this->validated = false;
        return $this;
    }

    /**
     * Get the item name.
     *
     * @return string
     */
    public function get_name(): string {
        return $this->name;
    }

    /**
     * Set the item name.
     *
     * @param string $name
     * @return static
     */
    public function set_name(string $name): static {
        $this->name      = $name;
        $this->validated = false;
        return $this;
    }

    /**
     * Get the maximum number of this item a user may hold (null = unlimited).
     *
     * @return int|null
     */
    public function get_maxnumber(): ?int {
        return $this->maxnumber;
    }

    /**
     * Set the maximum number of this item a user may hold.
     *
     * @param int|null $maxnumber
     * @return static
     */
    public function set_maxnumber(?int $maxnumber): static {
        $this->maxnumber = $maxnumber;
        $this->validated = false;
        return $this;
    }

    /**
     * Get the rich text description.
     *
     * @return string|null
     */
    public function get_detail(): ?string {
        return $this->detail;
    }

    /**
     * Set the rich text description.
     *
     * @param string|null $detail
     * @return static
     */
    public function set_detail(?string $detail): static {
        $this->detail    = $detail;
        $this->validated = false;
        return $this;
    }

    /**
     * Get the format of the detail field.
     *
     * @return int|null
     */
    public function get_detailformat(): ?int {
        return $this->detailformat;
    }

    /**
     * Set the format of the detail field.
     *
     * @param int|null $detailformat
     * @return static
     */
    public function set_detailformat(?int $detailformat): static {
        $this->detailformat = $detailformat;
        $this->validated    = false;
        return $this;
    }

    /**
     * Get the total stock limit (null = unrestricted).
     *
     * @return int|null
     */
    public function get_amountlimit(): ?int {
        return $this->amountlimit;
    }

    /**
     * Set the total stock limit.
     *
     * @param int|null $amountlimit
     * @return static
     */
    public function set_amountlimit(?int $amountlimit): static {
        $this->amountlimit = $amountlimit;
        $this->validated   = false;
        return $this;
    }

    /**
     * Get the current stock remaining.
     *
     * @return int|null
     */
    public function get_currentamount(): ?int {
        return $this->currentamount;
    }

    /**
     * Set the current stock remaining.
     *
     * @param int|null $currentamount
     * @return static
     */
    public function set_currentamount(?int $currentamount): static {
        $this->currentamount = $currentamount;
        $this->validated     = false;
        return $this;
    }

    // -----------------------------------------------------------------------
    // Business methods
    // -----------------------------------------------------------------------

    /**
     * Is an item in a specific stash?
     *
     * @param int $itemid The item ID.
     * @param int $stashid The stash ID.
     * @return bool
     */
    public static function is_item_in_stash($itemid, $stashid) {
        global $DB;
        $sql = "SELECT i.id
                  FROM {" . self::TABLE . "} i
                 WHERE i.id = ?
                   AND i.stashid = ?";
        return $DB->record_exists_sql($sql, [$itemid, $stashid]);
    }

    /**
     * Is there a limit to how many of this item a user can have at once?
     *
     * @return bool
     */
    public function is_unlimited() {
        return $this->maxnumber === null;
    }

    /**
     * Is this item a scarce item? If the amount limit is above zero then yes.
     *
     * @return bool
     */
    public function is_scarce_item() {
        return !empty($this->amountlimit);
    }

    /**
     * Do we have enough of the scarce item for the requested amount?
     *
     * @param int $quantity Quantity requested.
     * @return bool True if we have enough, false otherwise.
     */
    public function scarce_item_available($quantity = 1) {
        return $this->currentamount >= $quantity;
    }

    // -----------------------------------------------------------------------
    // Validation hooks
    // -----------------------------------------------------------------------

    /**
     * Validate the max number.
     *
     * Null means unlimited. Zero does not have a meaning at the moment.
     *
     * @param int|null $value The value.
     * @return true|lang_string
     */
    protected function validate_maxnumber($value) {
        if ($value !== null && $value <= 0) {
            return new lang_string('invaliddata', 'error');
        }
        return true;
    }

    /**
     * Validate the stash ID.
     *
     * @param int $value The stash ID.
     * @return true|lang_string
     */
    protected function validate_stashid($value) {
        if (!stash::record_exists($value)) {
            return new lang_string('invaliddata', 'error');
        }
        return true;
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
     * Check whether any record matches a raw WHERE clause.
     *
     * @param string     $select
     * @param array|null $params
     * @return bool
     */
    public static function record_exists_select(string $select, ?array $params = null): bool {
        global $DB;
        return $DB->record_exists_select(static::TABLE, $select, $params);
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
                $propertyname        = substr($property, $prefixlength);
                $data->$propertyname = $value;
            }
        }
        return $data;
    }

}
