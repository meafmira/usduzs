<?php
use Carbon\Carbon;
class KursController extends \BaseController {

	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	private function evaluateTodayAverage($type = 'buy') {
		$dt = Carbon::now();
		$day = $dt->day;
		$year = $dt->year;
		$month = $dt->month;
		$avg = Kurs::where('type', '=', $type)
			->whereBetween('created_at', array("$year-$month-$day 00:00:00", "$year-$month-$day 23:59:59"))
			->orderBy('created_at')
			->avg('kurs');

		#$value = Values::where('name', '=', 'today_average_'.$type)->first();
		#if ($value == NULL) {
		#		$value = new Values();
		#		$value->name = 'average_'.$type;
		#}
		#$value->value = $avg;
		#$value->save();

		return $avg;
	}

	private function evaluateAverage($type = 'buy') {
		$avg = Kurs::where('type', '=', $type)
			->orderBy('created_at')
			->take(1000)
			->avg('kurs');

		$value = Values::where('name', '=', 'average_'.$type)->first();
		if ($value == NULL) {
			$value = new Values();
			$value->name = 'average_'.$type;
		}
		$value->value = $avg;
		$value->save();

		return $avg;
	}

	private function evaluateS($type = 'buy') {
		$avg = $this->getAverage($type);
		$kurses = Kurs::where('type', '=', $type)
			->orderBy('created_at')
			->take(1000)
			->get()
			->toArray();
		$n = count($kurses);
		$sum = array_reduce($kurses, function ($carry, $kurs) use ($avg) {
			return pow($kurs['kurs'] - $avg, 2) + $carry;
		}, 0);
		$s = sqrt($sum / ($n - 1));

		$value = Values::where('name', '=', 's_'.$type)->first();
		if ($value == NULL) {
			$value = new Values();
			$value->name = 's_'.$type;
		}
		$value->value = $s;
		$value->save();

		return $s;
	}

	private function getAverage($type = 'buy') {
		$avg = Values::where('name', '=', 'average_'.$type)->pluck('value');

		if ($avg == NULL) {
			$avg = $this->evaluateAverage($type);
		}

		return $avg;
	}

	private function getS($type = 'buy') {
		$s = Values::where('name', '=', 's_'.$type)->pluck('value');

		if ($s == NULL) {
			$s = $this->evaluateS($type);
		}

		return $s;
	}

	public function index()
	{
		#$buyLastValue = Kurs::where('type', '=', 'buy')->orderBy('created_at')->first();
		#$sellLastValue = Kurs::where('type', '=', 'sell')->orderBy('created_at')->first();
		$buyLastValue = $this->evaluateTodayAverage('buy');
		$sellLastValue = $this->evaluateTodayAverage('sell');
		$buyCount = Kurs::where('type', '=', 'buy')->count();
		$sellCount = Kurs::where('type', '=', 'sell')->count();
		$buyS = $this->getS('buy');
		$sellS = $this->getS('sell');
		$buyDiff = $buyS * (2/sqrt($buyCount + 1) + 0.6);
		$sellDiff = $sellS * (2/sqrt($sellCount + 1) + 0.6);
		$buyMax = $buyLastValue + $buyDiff;
		$buyMin = $buyLastValue - $buyDiff;
		$sellMax = $sellLastValue + $sellDiff;
		$sellMin = $sellLastValue - $sellDiff;
		return [
			"buyS" => $buyS,
			"sellS" => $sellS,
			"buyMax" => $buyMax,
			"buyMin" => $buyMin,
			"sellMax" => $sellMax,
			"sellMin" => $sellMin,
			"buyAverage" => $this->evaluateTodayAverage('buy'),
			"sellAverage" => $this->evaluateTodayAverage('sell')
		];
	}


	/**
	 * Show the form for creating a new resource.
	 *
	 * @return Response
	 */
	public function create()
	{
		//
	}


	/**
	 * Store a newly created resource in storage.
	 *
	 * @return Response
	 */
	public function store()
	{
		$kurs = new Kurs();
		$kurs->type = Input::get('type');
		$kurs->kurs = Input::get('kurs');
		$kurs->save();
		$avgBuy = $this->evaluateAverage('buy');
		$avgSell = $this->evaluateAverage('sell');
		$sBuy = $this->evaluateS('buy');
		$sSell = $this->evaluateS('sell');
		return [
			"buyS" => $sBuy,
			"sellS" => $sSell,
			"buyAverage" => $avgBuy,
			"sellAverage" => $avgSell,
			"kurs" => $kurs
		];
	}


	/**
	 * Display the specified resource.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function show($id)
	{
		//
	}


	/**
	 * Show the form for editing the specified resource.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function edit($id)
	{
		//
	}


	/**
	 * Update the specified resource in storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function update($id)
	{
		//
	}


	/**
	 * Remove the specified resource from storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function destroy($id)
	{
		//
	}


}
