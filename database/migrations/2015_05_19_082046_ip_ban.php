<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class IpBan extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('kurs', function(Blueprint $table)
		{
			$table->string('ip');
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('kurs', function(Blueprint $table)
		{
			$table->dropColumn('ip');
		});
	}

}
