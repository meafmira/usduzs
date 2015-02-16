<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class BuyToSellKurs extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('kurs', function(Blueprint $table)
		{
			//
      DB::statement("ALTER TABLE kurs CHANGE COLUMN `type` `type` ENUM('sell', 'buy', 'change')");
      DB::table('kurs')->where('type', '=', 'buy')->update([ 'type' => 'change' ]);
      DB::table('kurs')->where('type', '=', 'sell')->update([ 'type' => 'buy' ]);
      DB::table('kurs')->where('type', '=', 'change')->update([ 'type' => 'sell' ]);
      DB::statement("ALTER TABLE kurs CHANGE COLUMN `type` `type` ENUM('sell', 'buy')");
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
			//
      DB::statement("ALTER TABLE kurs CHANGE COLUMN `type` `type` ENUM('sell', 'buy', 'change')");
      DB::table('kurs')->where('type', '=', 'buy')->update([ 'type' => 'change' ]);
      DB::table('kurs')->where('type', '=', 'sell')->update([ 'type' => 'buy' ]);
      DB::table('kurs')->where('type', '=', 'change')->update([ 'type' => 'sell' ]);
      DB::statement("ALTER TABLE kurs CHANGE COLUMN `type` `type` ENUM('sell', 'buy')");
		});
	}

}
