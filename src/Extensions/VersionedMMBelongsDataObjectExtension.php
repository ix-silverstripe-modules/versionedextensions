<?php

namespace Internetrix\VersionedExtensions\Extensions;

use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataQuery;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\Versioned\Versioned;

/**
 * 
 * @author jason.zhang@internetrix.com.au
 * @package versionedextensions
 * 
 */
class VersionedMMBelongsDataObjectExtension extends DataExtension
{
	/**
	 * Update any requests to show published many many relationship.
	 */
	function augmentSQL(SQLSelect $query, DataQuery $dataQuery = null)
    {
		// Don't run on delete queries
		if ($query->getDelete()) return;
	
		//only show published relationship when it's Live mode.
		if (Versioned::get_stage() == 'Live') {
			//apply where condition if this query is based on many_many relationship
			//get all the belongs_many_many info
			$belongs_many_many = $this->owner->config()->get('belongs_many_many');
			
			if (!empty($belongs_many_many)) {
				$tables = $query->getFrom();
				
				foreach ($belongs_many_many as $componentName => $componentClassName){
					if ($componentClassName::has_extension(VersionedMMPageExtension::class)) {
						//e.g.  'Slide',   'Page',         'SlideID',     'PageID',        'Page_Slides'
						list($parentClass, $componentClass, $parentField, $componentField, $MMTable) = $this->owner->many_many($componentName);
						
						$LiveMMTable 	= $MMTable . '_Live';
						
						//only apply Live MM relationship condition only if the query has included MM table.
						if (key_exists($MMTable, $tables)) {
							$MMQuery = $tables[$MMTable];
							
							$query->addLeftJoin($LiveMMTable, "\"{$LiveMMTable}\".\"{$parentField}\" = \"{$parentClass}\".\"ID\"");
							
							$query->addWhere("\"{$LiveMMTable}\".\"{$componentField}\" IS NOT NULL");
						}
					}
				}
			}
		}
	}
}
