<?php

namespace Kieran\Bans;

use XF\Db\Schema\Create;

class Setup extends \XF\AddOn\AbstractSetup
{
	use \XF\AddOn\StepRunnerInstallTrait;
	use \XF\AddOn\StepRunnerUpgradeTrait;
	use \XF\AddOn\StepRunnerUninstallTrait;

	// php cmd.php xf-addon:install Kieran/Bans
	// php cmd.php xf-addon:build-release Kieran/Bans

	public function installStep1(array $stepParams = [])
	{
		$this->schemaManager()->createTable('xf_kieran_bans', function(Create $table)
		{
			$table->addColumn('ban_id', 'int')->autoIncrement();
			$table->addColumn('server_ip', 'varchar', 55)->setDefault('website');
			$table->addColumn('admin_id', 'bigint', 64);
			$table->addColumn('admin_user_id', 'int', 11)->setDefault(0);
			$table->addColumn('admin_ip', 'varchar', 55);
			$table->addColumn('admin_name', 'varchar', 64);
			$table->addColumn('target_id', 'bigint', 64);
			$table->addColumn('target_ip', 'varchar', 55);
			$table->addColumn('target_name', 'varchar', 65);
			$table->addColumn('ban_length', 'int', 11);
			$table->addColumn('ban_reason', 'varchar', 255);
			$table->addColumn('ban_status', 'int', 1)->setDefault(0);
            $table->addColumn('type_id', 'varbinary', 25);
			$table->addColumn('ban_review', 'int', 1)->setDefault(0);
			$table->addColumn('attach_count', 'smallint', 5)->setDefault(0);
			$table->addColumn('timestamp', 'int', 11);
			$table->addPrimaryKey('ban_id');
			$table->addUniqueKey(['ban_id'], 'ban_id');
		});

		$this->schemaManager()->createTable('xf_kieran_bans_notes', function(Create $table)
		{
			$table->addColumn('note_id', 'int')->autoIncrement();
			$table->addColumn('ban_id', 'int', 11);
			$table->addColumn('user_id', 'int', 11);
			$table->addColumn('visible', 'int', 1)->setDefault(1);
			$table->addColumn('note', 'longtext');
			$table->addColumn('attach_count', 'smallint', 5)->setDefault(0);
			$table->addColumn('data', 'text');
			$table->addColumn('timestamp', 'int', 11);
			$table->addPrimaryKey('note_id');
			$table->addUniqueKey(['note_id', 'ban_id', 'user_id'], 'note_id_ban_id_user_id');
        });

		$this->schemaManager()->createTable('xf_kieran_bans_type', function(Create $table)
		{
			$table->addColumn('type_id', 'varbinary', 25);
			$table->addColumn('name', 'varchar', 55);
			$table->addPrimaryKey('type_id');
			$table->addUniqueKey(['type_id'], 'type_id');
        });
        
        $status = $this->app->em()->create('Kieran\Bans:BanType');
		$status->type_id = 'server';
		$status->name = 'Server';
		$status->save();
	}
	
	public function upgrade(array $stepParams = [])
	{
	}
	
	public function uninstallStep1(array $stepParams = [])
	{
		$this->schemaManager()->dropTable('xf_kieran_bans');
		$this->schemaManager()->dropTable('xf_kieran_bans_notes');
		$this->schemaManager()->dropTable('xf_kieran_bans_type');
	}

}