<?php
use Carbon\Carbon;
class KursController extends \BaseController {

	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */

	private function getToday() {
		$dt = Carbon::now();
		$day = $dt->day;
		$year = $dt->year;
		$month = $dt->month;
		return "$year-$month-$day";
	}

	private function evaluateDateAverage($date, $type = 'buy') {
		$avg = Kurs::where('type', '=', $type)
			->whereBetween('created_at', array("$date 00:00:00", "$date 23:59:59"))
			->orderBy('created_at')
			->avg('kurs');

		return $avg;
	}

	private function evaluateTodayAverage($type = 'buy') {
		$dt = Carbon::now();
		$day = $dt->day;
		$year = $dt->year;
		$month = $dt->month;
		return $this->evaluateDateAverage("$year-$month-$day", $type);
	}

	private function getDateCount($date, $type = NULL) {
		if (isset($type)) {
			$count = Kurs::where('type', '=', $type)
				->whereBetween('created_at', array("$date 00:00:00", "$date 23:59:59"))
				->count();
		}
		else {
			$count = Kurs::whereBetween('created_at', array("$date 00:00:00", "$date 23:59:59"))
				->count();
		}
		return $count;
	}

	private function getTodayCount($type = NULL) {
		$dt = Carbon::now();
		$day = $dt->day;
		$year = $dt->year;
		$month = $dt->month;
		return $this->getDateCount("$year-$month-$day", $type);
	}

	private function evaluateYesterdayAverage($type = 'buy') {
		$dt = Carbon::yesterday();
		$day = $dt->day;
		$year = $dt->year;
		$month = $dt->month;
		return $this->evaluateDateAverage("$year-$month-$day", $type);
	}

	private function evaluateAverage($type = 'buy') {
		$avg = Kurs::where('type', '=', $type)
			->orderBy('created_at')
			->distinct()
			->select('kurs')
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
      ->select(DB::raw('*, ROUND(AVG(kurs)) as avg'))
      ->groupBy(DB::raw('year(created_at), month(created_at), day(created_at), type'))
			->take(1000)
			->get()
			->toArray();
		$n = count($kurses);
		$sum = array_reduce($kurses, function ($carry, $kurs) use ($avg) {
			return pow($kurs['avg'] - $avg, 2) + $carry;
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

	private function dataCount($type = 'buy') {
		return Kurs::where('type', '=', $type)->count();
	}

	private function getDiff($type = 'buy') {
		$s = $this->getS($type);
		$count = $this->dataCount($type);
		$nt = (3/sqrt($count + 1) + 1.15);
		$diff = $s * $nt;
		return $diff;
	}

	private function getMinMax($type = 'buy') {
		$diff = $this->getDiff($type);
		$todayAverage = $this->evaluateTodayAverage($type);
		if (!isset($todayAverage)) {
			$todayAverage = $this->evaluateYesterdayAverage($type);
		}
		return [
			"min" => $todayAverage - $diff,
			"max" => $todayAverage + $diff
		];
	}

	public function index()
	{
		$buyMinMax = $this->getMinMax('buy');
		$buyMax = round($buyMinMax['max']);
		$buyMin = round($buyMinMax['min']);
		$sellMinMax = $this->getMinMax('sell');
		$sellMax = round($sellMinMax['max']);
		$sellMin = round($sellMinMax['min']);
		$buyAverage = $this->evaluateTodayAverage('buy');
		$sellAverage = $this->evaluateTodayAverage('sell');
		$todayCount = $this->getTodayCount();
		if ($buyAverage) {
			$buyAverage = round($buyAverage);
		}
		if ($sellAverage) {
			$sellAverage = round($sellAverage);
		}
		return [
			"buyMax" => $buyMax,
			"buyMin" => $buyMin,
			"sellMax" => $sellMax,
			"sellMin" => $sellMin,
			"buyAverage" => $buyAverage,
			"sellAverage" => $sellAverage,
			"todayCount" => $todayCount
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
		$type = Input::get('type');
		$kurs = intval(Input::get('kurs'));
		$minMax = $this->getMinMax($type);
		$min = round($minMax['min']);
		$max = round($minMax['max']);
		$validator = Validator::make(
			[ 'kurs' => $kurs ],
			[ 'kurs' => "integer|between:$min,$max" ]
		);
		if ($validator->fails()) {
			return Response::make($validator->messages(), 400);
		}
		else {
			$kursObj = new Kurs();
			$kursObj->type = $type;
			$kursObj->kurs = $kurs;
			$kursObj->save();
			$avgBuy = $this->evaluateAverage('buy');
			$avgSell = $this->evaluateAverage('sell');
			$sBuy = $this->evaluateS('buy');
			$sSell = $this->evaluateS('sell');
			return [
				"buyS" => $sBuy,
				"sellS" => $sSell,
				"buyAverage" => $avgBuy,
				"sellAverage" => $avgSell,
				"kurs" => $kursObj
			];
		}
	}


	/**
	 * Display the specified resource.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function show($date)
	{
		$dateString = Carbon::createFromFormat('d-m-Y', $date)->toDateString();
		return Kurs::whereBetween('created_at', array("$dateString 00:00:00", "$dateString 23:59:59"))
			->orderBy('created_at', 'desc')
			->get();
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

	public function vote($id) {
		$kurs = Kurs::find($id);
		$kurs->vote++;
		$kurs->save();
		return $kurs;
	}

  public function dayAverages($dayBack = 6) {
    $dt = Carbon::today()->subDays($dayBack);
    return Kurs::select(DB::raw('*, day(created_at) as day, ROUND(AVG(kurs)) as avgKurs'))
      ->orderBy('created_at', 'asc')
      ->groupBy(DB::raw('year(created_at), month(created_at), day(created_at), type'))
      ->where('created_at', ">=", $dt)
      ->get();
  }

}
