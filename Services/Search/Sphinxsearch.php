<?php

namespace Search\SphinxsearchBundle\Services\Search;

use SphinxClient;

class Sphinxsearch
{
    /**
     * @var array $hosts
     */
    protected $hosts;

    /**
     * @var array $loads
     */
    protected $loads;


    /**
     * @var string $port
     */
    protected $port;

    /**
     * @var string $socket
     */
    protected $socket;

    /**
     * @var array $indexes
     *
     * $this->indexes should look like:
     *
     * $this->indexes = array(
     *   'IndexLabel' => 'Index name as defined in sphinxsearch.conf',
     *   ...,
     * );
     */
    private $indexes;

    /**
     * @var SphinxClient $sphinx
     */
    private $sphinx;

    /**
     * @param array  $hosts
     * @param array  $loads
     * @param string $port
     * @param null   $socket
     * @param array  $indexes
     */
    public function __construct($hosts = array(), $loads = array(), $port = '9312', $socket = null, array $indexes = array())
    {
        $this->hosts   = $hosts;
        $this->loads   = $loads;
        $this->port    = $port;
        $this->socket  = $socket;
        $this->indexes = $indexes;

        $this->sphinx = new \SphinxClient();

        if ($this->socket !== null) {
            $this->sphinx->setServer($this->socket);
        } else {
            $this->sphinx->setServer($this->hosts, $this->loads, $this->port);
        }
    }

    /**
     * Escape the supplied string.
     *
     * @param string $string The string to be escaped.
     *
     * @return string The escaped string.
     */
    public function escapeString($string)
    {
        return $this->sphinx->escapeString($string);
    }

    /**
     * Set the desired match mode.
     *
     * @param int $mode The matching mode to be used.
     */
    public function setMatchMode($mode)
    {
        $this->sphinx->setMatchMode($mode);
    }

    /**
     * Set the ranking mode
     *
     * @param int    $ranker SPH_RANK_* constant
     * @param string $rankExpr
     */
    public function setRankingMode($ranker, $rankExpr = '')
    {
        $this->sphinx->SetRankingMode($ranker, $rankExpr);
    }

    /**
     * @param $mode
     * @param $field
     */
    public function setSortMode($mode, $field)
    {
        $this->sphinx->setSortMode($mode, $field);
    }

    public function resetSortMode()
    {
        $this->sphinx->_sortby = '';
    }

    /**
     * Set limits on the range and number of results returned.
     *
     * @param int $offset The number of results to seek past.
     * @param int $limit  The number of results to return.
     * @param int $max    The maximum number of matches to retrieve.
     * @param int $cutoff The cutoff to stop searching at.
     */
    public function setLimits($offset, $limit, $max = 0, $cutoff = 0)
    {
        $this->sphinx->setLimits($offset, $limit, $max, $cutoff);
    }

    /**
     * Set weights for individual fields.  $weights should look like:
     *
     * $weights = array(
     *   'Normal field name' => 1,
     *   'Important field name' => 10,
     * );
     *
     * @param array $weights Array of field weights.
     */
    public function setFieldWeights(array $weights)
    {
        $this->sphinx->setFieldWeights($weights);
    }

    /**
     * Set the desired search filter.
     *
     * @param string $attribute The attribute to filter.
     * @param array  $values    The values to filter.
     * @param bool   $exclude   Is this an exclusion filter?
     */
    public function setFilter($attribute, $values, $exclude = false)
    {
        $this->sphinx->setFilter($attribute, $values, $exclude);
    }

    /**
     * Reset all previously set filters.
     */
    public function resetFilters()
    {
        $this->sphinx->resetFilters();
    }

    /**
     * Set the format of the search result array:
     *  $arrayResult = FALSE (default): Result will be a hash indexed by document ids
     *  $arrayResult = TRUE:            Result will be an array where document ids are provided as '@id' attribute.
     *
     * @param bool $arrayResult should be TRUE or FALSE
     */
    public function setArrayResult($arrayResult)
    {
        $this->sphinx->SetArrayResult($arrayResult);
    }

    /**
     * Set grouping
     *
     * @param string $attribute
     * @param string $func
     * @param string $groupSort
     */
    public function setGroupBy($attribute, $func, $groupSort = '@groupby desc')
    {
        $this->sphinx->SetGroupBy($attribute, $func, $groupSort);
    }

    /**
     * Disable grouping
     */
    public function resetGroupBy()
    {
        $this->sphinx->ResetGroupBy();
    }

    /**
     * Count the number of distinct attribute2 values per group while grouping by some other attribute @see setGroupBy()
     *
     * @param string $attribute2
     */
    public function setGroupDistinct($attribute2)
    {
        $this->sphinx->SetGroupDistinct($attribute2);
    }

    /**
     * @param       $query
     * @param array $indexes
     * @param array $options
     * @param bool  $escapeQuery
     *
     * @return array|bool
     * @throws \RuntimeException
     */
    public function search($query, array $indexes, array $options = array(), $escapeQuery = true)
    {
        if ($escapeQuery) {
            $query = $this->sphinx->escapeString($query);
        }

        /**
         * Build the list of indexes to be queried.
         */
        $indexNames = '';

        foreach ($indexes as &$label) {
            if (isset($this->indexes[$label])) {
                $indexNames .= $this->indexes[$label] . ' ';
            }
        }

        /**
         * If no valid indexes were specified, return an empty result set.
         *
         * FIXME: This should probably throw an exception.
         */
        if (empty($indexNames)) {
            return array();
        }

        /**
         * Set the offset and limit for the returned results.
         */
        if (isset($options['result_offset']) && isset($options['result_limit'])) {
            $this->sphinx->setLimits($options['result_offset'], $options['result_limit']);
        }

        /**
         * Weight the individual fields.
         */
        if (isset($options['field_weights'])) {
            $this->sphinx->setFieldWeights($options['field_weights']);
        }

        /**
         * Perform the query.
         */
        $results = $this->sphinx->query($query, $indexNames);

        if ($results['status'] !== SEARCHD_OK) {
            throw new \RuntimeException(sprintf('Searching index "%s" for "%s" failed with error "%s".', $label, $query, $this->sphinx->getLastError()));
        }

        return $results;
    }

    /**
     * Adds a query to a multi-query batch using current settings.
     *
     * @param string $query   The query string that we are searching for.
     * @param array  $indexes The indexes to perform the search on.
     */
    public function addQuery($query, array $indexes)
    {
        $indexNames = '';

        foreach ($indexes as &$label) {
            if (isset($this->indexes[$label])) {
                $indexNames .= $this->indexes[$label] . ' ';
            }
        }

        if (!empty($indexNames)) {
            $this->sphinx->addQuery($query, $indexNames);
        }
    }

    /**
     * Runs the currently batched queries, and returns the results.
     *
     * @return array The results of the queries.
     */
    public function runQueries()
    {
        return $this->sphinx->runQueries();
    }
}
