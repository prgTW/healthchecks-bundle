<?php
/**
 * Created by umitakkaya.
 * Date: 28/08/2017
 * Time: 14:56
 */

namespace prgTW\HealthchecksBundle\Resolver;


interface ResolverInterface
{
	/**
	 * Returns checks data or an empty array if there is none.
	 *
	 * Example data to be returned:
	 * <code>
	 * [
	 *     'check_key' => [
	 *         'name'     => 'Check name',       // required
	 *         'client'   => 'client_name',      // required
	 *         'timeout'  => 3600,               // optional, default: null
	 *         'grace'    => 1800,               // optional, default: 1800
	 *         'schedule' => '* * * * *',        // optional, default: '* * * * *'
	 *         'channels' => '*',                // optional, default: null
	 *         'unique'   => ['name', 'tags'],   // optional, default: ['name']
	 *         'timezone  => 'Europe/Warsaw'     // optional, default: null or the timezone specified in bundle config
	 *     ],
	 * ]
	 * </code>
	 *
	 * @return array
	 */
	public function resolve(): array;

	/**
	 * Returns names of the checks in the following form.
	 *
	 * <code>
	 * [ 'check_key', 'check_key_2', 'check_key_3' ]
	 * </code>
	 *
	 * @return array
	 */
	public function resolveNames(): array;
}