<?php 
/**
 * Adds all the necesary functions to Page.php.
 * When an action is performed on a page i.e publish, unpublish etc, this extension will check the has_many relationships defined 
 * in $has_many and perform the required action on those dataobjects that extend VersionedHMDataObjectExtension
 * 
 * 
 * @author guy.watson@internetrix.com.au
 * @package versionedextensions
 *
 */
class VersionedHMPageExtension extends DataExtension{

	
	function updateCMSActions(FieldList $actions){
		$publish 		 = $actions->dataFieldByName('action_publish');
		$deleteDraft 	 = $actions->dataFieldByName('action_delete') ? 'action_delete' : null;
		$rollback 		 = $actions->dataFieldByName('action_rollback');
		$modifiedOnStage = $this->owner->getIsModifiedOnStage();
		
		if($modifiedOnStage && $publish){
			$publish->addExtraClass('ss-ui-alternate');
		}
		
		if(!$rollback && $modifiedOnStage && !$this->owner->IsDeletedFromStage){
			if($this->owner->isPublished() && $this->owner->canEdit())	{
				// "rollback"
				$actionMenus = $actions->fieldByName('ActionMenus');
				if( !$actionMenus ){ // make sure there is a menu to append to
				    $actionMenus = TabSet::create('ActionMenus');
				    $form = $actions->first()->getForm();
				    $actionMenus->setForm($form);
				    $actions->push($actionMenus);
				}
				$actions->addFieldToTab('ActionMenus.MoreOptions',
					FormAction::create('rollback', _t('SiteTree.BUTTONCANCELDRAFT', 'Cancel draft changes'), 'delete')
						->setDescription(_t('SiteTree.BUTTONCANCELDRAFTDESC', 'Delete your draft and revert to the currently published page'))
					,$deleteDraft);
			}
		}
	}
	
	public function onAfterPublish(){
		if($this->owner->ID){
			// remove fields on the live table which could have been orphaned.
			$has_many = $this->owner->config()->get('has_many');
			if($has_many){
				foreach($has_many as $key => $value){
					$value = $this->getClassFromValue($value);
					if($value::has_extension('VersionedHMDataObjectExtension')) {
						$relationship 	= $this->owner->getComponents($key);
						$foreignKey 	= $relationship->getForeignKey();
						$live 			= Versioned::get_by_stage($value, "Live", "\"$foreignKey\" = " . $this->owner->ID );
				
						if($live) {
							foreach($live as $field) {
								$field->doDeleteFromStage('Live');
							}
						}
				
						// publish the draft pages
						if($relationship) {
							foreach($relationship as $field) {
								$field->doPublish('Stage', 'Live');
							}
						}
					}
				}
			}
		}
	}
	
	public function onBeforeUnpublish(){
		$has_many = $this->owner->config()->get('has_many');
		if($has_many){
			foreach($has_many as $key => $value){
				$value = $this->getClassFromValue($value);
				if($value::has_extension('VersionedHMDataObjectExtension')) {
					$relationship = $this->owner->getComponents($key);
					if($relationship) {
						foreach($relationship as $field) {
							$field->doDeleteFromStage('Live');
						}
					}
				}
			}
		}
	}
	
	public function onBeforeRevertToLive(){
		$has_many = $this->owner->config()->get('has_many');
		if($has_many){
			foreach($has_many as $key => $value){
				$value = $this->getClassFromValue($value);
				if($value::has_extension('VersionedHMDataObjectExtension')) {
					$relationship = $this->owner->getComponents($key);
					if($relationship) {
						foreach($relationship as $field) {
							$field->doRevertToLive();
							$field->delete();
						}
					}
				}
			}
		}
		
		$oldMode = Versioned::get_reading_mode();
		Versioned::set_reading_mode("Stage.Live");
		//move from live to staging
		$has_many = $this->owner->config()->get('has_many');
		if($has_many){
			foreach($has_many as $key => $value){
				$value = $this->getClassFromValue($value);
				if($value::has_extension('VersionedHMDataObjectExtension')) {
					$relationship = $this->owner->getComponents($key);
					if($relationship) {
						foreach($relationship as $field) {
							$field->publish("Live", "Stage", false);
							$field->writeWithoutVersion();
						}
					}
				}
			}
		}
		//now delete the live objects and move what is one stage to live.
		Versioned::set_reading_mode($oldMode);
	}
	
	public function onAfterDuplicate(Page $page){
		
		$has_many = $this->owner->config()->get('has_many');
		if($has_many){
			foreach($has_many as $key => $value){
				$value = $this->getClassFromValue($value);
				if($value::has_extension('VersionedHMDataObjectExtension')) {
					$relationship = $this->owner->getComponents($key);
					if($relationship) {
						foreach($relationship as $field) {
							$field->doDuplicate();
							$newField = $field->duplicate($page);
							$newField->ParentID = $page->ID;
							$newField->write();
						}
					}
				}
			}
		}
	}
	
	public function getIsModifiedOnStage(&$isModified){
		if(!$isModified) {
			$has_many = $this->owner->config()->get('has_many');
			if($has_many){
				foreach($has_many as $key => $value){
					$value = $this->getClassFromValue($value);
					if($value::has_extension('VersionedHMDataObjectExtension')) {
						$relationship = $this->owner->getComponents($key);
						if($relationship) {
							foreach($relationship as $field) {
								if($field->getIsModifiedOnStage()) {
									$isModified = true;
									break;
								}
							}
						}
					}
				}
			}
		}
		return $isModified;
	}
	
	public function onAfterRollback($version){
		if($version == "Live"){
			$this->owner->doRevertToLive(); //this will eventually call onBeforeRevertToLive() (see above)
		}
	}
	
	public function getClassFromValue($value){
		if(stripos($value, ".") !== false){
			$matches = explode(".", $value);
			return isset($matches[0]) ? $matches[0] : $value;
		}
		return $value;
	}
	
	public function getForeignKeyRemoteClass($remoteClass, $foreignKey){
		$remoteRelations = Config::inst()->get($remoteClass, 'has_one');

		foreach($remoteRelations as $key => $value){
			if($key . 'ID' == $foreignKey){
				return $value;
			}
		}
		return $remoteClass;
	}
}