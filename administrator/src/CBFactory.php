<?php

/**
 * @package     ContentBuilder NG
 * @copyright   Copyright © 2005 - 2020 Open Source Matters, Inc. All rights reserved.
 * @copyright   (C) 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace CB\Component\Contentbuilderng\Administrator;

// No direct access
\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

if (!class_exists(__NAMESPACE__ . '\\CBFactory')) {
	class CBFactory
	{

		private static $dbo = null;

		public static function getDbo()
		{

			if (static::$dbo == null) {
				static::$dbo = new CBDbo();
			}

			return static::$dbo;
		}
	}
}

if (!class_exists(__NAMESPACE__ . '\\CBDbo')) {
	class CBDbo
	{

		private $errNo = 0;
		private $errMsg = '';
		private $dbo = null;
		private $last_query = true;
		private $last_failed_query = '';

		function __construct()
		{
			$this->dbo = Factory::getContainer()->get(DatabaseInterface::class);
		}

		public function setQuery($query, $offset = 0, $limit = 0)
		{

			try {
				$this->dbo->setQuery($query, $offset, $limit);
			} catch (\Exception $e) {

				$this->last_query = false;
				$this->last_failed_query = $query;
				$this->errNo = $e->getCode();
				$this->errMsg = $e->getMessage();
			}
		}

		public function loadObjectList()
		{

			if (!$this->last_query) return array();

			$this->errNo = 0;
			$this->errMsg = '';

			try {
				return $this->dbo->loadObjectList();
			} catch (\Exception $e) {

				$this->errNo = $e->getCode();
				$this->errMsg = $e->getMessage();
			} catch (Error $e) {

				$this->errNo = $e->getCode();
				$this->errMsg = $e->getMessage();
			}

			return array();
		}

		public function loadObject($class = 'stdClass')
		{
			if (!$this->last_query) return null;

			$this->errNo = 0;
			$this->errMsg = '';

			try {
				return $this->dbo->loadObject($class);
			} catch (\Exception $e) {

				$this->errNo = $e->getCode();
				$this->errMsg = $e->getMessage();
			} catch (Error $e) {

				$this->errNo = $e->getCode();
				$this->errMsg = $e->getMessage();
			}

			return null;
		}

		public function loadColumn($offset = 0)
		{
			if (!$this->last_query) return null;

			$this->errNo = 0;
			$this->errMsg = '';

			try {
				return $this->dbo->loadColumn($offset);
			} catch (\Exception $e) {

				$this->errNo = $e->getCode();
				$this->errMsg = $e->getMessage();
			} catch (Error $e) {
				$this->errNo = $e->getCode();
				$this->errMsg = $e->getMessage();
			}

			return null;
		}

		public function loadAssocList($key = null, $column = null)
		{
			if (!$this->last_query) return array();

			$this->errNo = 0;
			$this->errMsg = '';

			try {
				return $this->dbo->loadAssocList($key, $column);
			} catch (\Exception $e) {
				$this->errNo = $e->getCode();
				$this->errMsg = $e->getMessage();
			} catch (Error $e) {
				$this->errNo = $e->getCode();
				$this->errMsg = $e->getMessage();
			}

			return array();
		}

		public function loadAssoc()
		{
			if (!$this->last_query) return null;

			$this->errNo = 0;
			$this->errMsg = '';

			try {
				return $this->dbo->loadAssoc();
			} catch (\Exception $e) {

				$this->errNo = $e->getCode();
				$this->errMsg = $e->getMessage();
			} catch (Error $e) {
				$this->errNo = $e->getCode();
				$this->errMsg = $e->getMessage();
			}

			return null;
		}

		public function query()
		{
			return $this->execute();
		}

		public function execute()
		{
			$this->errNo = 0;
			$this->errMsg = '';

			try {
				return $this->dbo->execute();
			} catch (\Exception $e) {
				$this->errNo = $e->getCode();
				$this->errMsg = $e->getMessage();
			} catch (Error $e) {
				$this->errNo = $e->getCode();
				$this->errMsg = $e->getMessage();
			}

			return false;
		}

		public function updateObject($table, &$object, $key, $nulls = false)
		{
			$this->errNo = 0;
			$this->errMsg = '';

			try {
				return $this->dbo->updateObject($table, $object, $key, $nulls);
			} catch (\Exception $e) {
				$this->errNo = $e->getCode();
				$this->errMsg = $e->getMessage();
			} catch (Error $e) {
				$this->errNo = $e->getCode();
				$this->errMsg = $e->getMessage();
			}

			return false;
		}

		public function insertObject($table, &$object, $key = null)
		{
			$this->errNo = 0;
			$this->errMsg = '';

			try {
				return $this->dbo->insertObject($table, $object, $key);
			} catch (\Exception $e) {
				$this->errNo = $e->getCode();
				$this->errMsg = $e->getMessage();
			} catch (Error $e) {
				$this->errNo = $e->getCode();
				$this->errMsg = $e->getMessage();
			}

			return false;
		}

		public function quote($query, $esc = true)
		{
			return $this->dbo->quote($query, $esc);
		}

		public function getQuery($new = false)
		{
			if (!$this->last_query) return $this->last_failed_query;

			return $this->dbo->getQuery($new);
		}

		public function getPrefix()
		{
			return $this->dbo->getPrefix();
		}

		public function getNullDate()
		{
			return $this->dbo->getNullDate();
		}

		public function getNumRows()
		{
			if (!$this->last_query) return 0;
			return $this->dbo->getNumRows();
		}

		public function getCount()
		{
			if (!$this->last_query) return 0;
			return $this->dbo->getCount();
		}

		public function getConnection()
		{
			return $this->dbo;
		}

		public function getAffectedRows()
		{
			if (!$this->last_query) return array();
			return $this->dbo->getAffectedRows();
		}

		public function getTableColumns($table, $typeOnly = true)
		{
			$this->errNo = 0;
			$this->errMsg = '';

			try {
				return $this->dbo->getTableColumns($table, $typeOnly);
			} catch (\Exception $e) {
				$this->errNo = $e->getCode();
				$this->errMsg = $e->getMessage();
			} catch (Error $e) {
				$this->errNo = $e->getCode();
				$this->errMsg = $e->getMessage();
			}

			return array();
		}

		public function getTableList()
		{
			$this->errNo = 0;
			$this->errMsg = '';

			try {
				return $this->dbo->getTableList();
			} catch (\Exception $e) {
				$this->errNo = $e->getCode();
				$this->errMsg = $e->getMessage();
			} catch (Error $e) {
				$this->errNo = $e->getCode();
				$this->errMsg = $e->getMessage();
			}

			return array();
		}

		public function loadResult()
		{
			if (!$this->last_query) return null;

			$this->errNo = 0;
			$this->errMsg = '';

			try {
				return $this->dbo->loadResult();
			} catch (\Exception $e) {
				$this->errNo = $e->getCode();
				$this->errMsg = $e->getMessage();
			} catch (Error $e) {

				$this->errNo = $e->getCode();
				$this->errMsg = $e->getMessage();
			}

			return null;
		}

		public function getErrorNum()
		{
			return $this->errNo;
		}

		public function getErrorMsg()
		{
			return $this->errMsg;
		}

		public function stderr()
		{
			return $this->errMsg;
		}

		public function insertid()
		{
			if (!$this->last_query) return 0;
			return $this->dbo->insertid();
		}
	}
}
