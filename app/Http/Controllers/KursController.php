<?php
use Carbon\Carbon;
use App\BannedIp;

class KursController extends Controller {

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

  private function evaluateLastAverage($type = 'sell', $hours = 24) {
    $dt = Carbon::now()->subHours($hours);
    $avg = Kurs::where('type', '=', $type)
      ->where('created_at', '>=', $dt)
      ->orderBy('created_at')
      ->avg('kurs');
    return $avg;
  }

	private function evaluateDateAverage($date, $type = 'buy', $place) {
		$avg = Kurs::where('type', '=', $type)
			->whereBetween('created_at', array("$date 00:00:00", "$date 23:59:59"))
			->orderBy('created_at');

    if (isset($place)) $avg = $avg->where('place', '=', $place);

		$avg = $avg->avg('kurs');

		return $avg;
	}

	private function evaluateTodayAverage($type = 'buy', $place) {
		$dt = Carbon::now();
		$day = $dt->day;
		$year = $dt->year;
		$month = $dt->month;
		return $this->evaluateDateAverage("$year-$month-$day", $type, $place);
	}

	private function getDateCount($date, $type = NULL, $place) {
		if (isset($type)) {
			$count = Kurs::where('type', '=', $type)
				->whereBetween('created_at', array("$date 00:00:00", "$date 23:59:59"));
		}
		else {
			$count = Kurs::whereBetween('created_at', array("$date 00:00:00", "$date 23:59:59"));
		}
    if (isset($place)) {
      $count = $count->where('place', '=', $place);
    }
    $count = $count->count();
		return $count;
	}

	private function getTodayCount($type = NULL, $place) {
		$dt = Carbon::now();
		$day = $dt->day;
		$year = $dt->year;
		$month = $dt->month;
		return $this->getDateCount("$year-$month-$day", $type, $place);
	}

	private function evaluateYesterdayAverage($type = 'buy', $place) {
		$dt = Carbon::yesterday();
		$day = $dt->day;
		$year = $dt->year;
		$month = $dt->month;
		return $this->evaluateDateAverage("$year-$month-$day", $type, $place);
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
		$nt = (3/sqrt($count + 1) + 2.15);
		$diff = $s * $nt;
		return $diff;
	}

	private function getMinMax($type = 'buy') {
		$diff = $this->getDiff($type);
		$todayAverage = $this->evaluateLastAverage($type);
    if (!isset($todayAverage)) {
      $todayAverage = $this->evaluateLastAverage($type, 48);
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
    $place = Input::get('place');
		$buyAverage = $this->evaluateTodayAverage('buy', $place);
    if (!isset($buyAverage)) {
      $buyAverage = $this->evaluateYesterdayAverage('buy', $place);
    }
		$sellAverage = $this->evaluateTodayAverage('sell', $place);
    if (!isset($sellAverage)) {
      $sellAverage = $this->evaluateYesterdayAverage('sell', $place);
    }
		$todayCount = $this->getTodayCount(NULL, $place);
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
	public function store(Request $request)
	{
		$type = Input::get('type');
		$kurs = intval(Input::get('kurs'));
    $place = Input::get('place');
		$ip = Request::getClientIp(true);
		$isBanned = BannedIp::where('ip', $ip)->count() == 0 ? false : true;
		if ($place == 'Админы сделайте модерацию по локациям и регистрацию пользователей') {
			if (!$isBanned) {
				$bannedIp = new BannedIp();
				$bannedIp->ip = $ip;
				$bannedIp->save();
				$isBanned = true;
			}
		}
		if ($isBanned) {
			return [ "message" => "Go away" ];
		}
		else {
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
				$kursObj->ip = Request::getClientIp(true);
	      if (isset($place)) {
	        $kursObj->place = $place;
	      }
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
	}


	/**
	 * Display the specified resource.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function show($date)
	{
    $place = Input::get('place');
		$dateString = Carbon::createFromFormat('d-m-Y', $date)->toDateString();
		$result = Kurs::whereBetween('created_at', array("$dateString 00:00:00", "$dateString 23:59:59"))
			->orderBy('created_at', 'desc');
    if (isset($place)) {
      $result = $result->where('place', '=', $place);
    }
    return $result->get();
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
    $place = Input::get('place');
    $key = "day_averages_$dayBack";
    if (isset($place)) {
      $key .= $place;
    }
    if (Cache::has($key)) {
      $result = Cache::get($key);
    }
    else {
      $dt = Carbon::today()->subDays($dayBack);
      $result = Kurs::select(DB::raw('*, day(created_at) as day, ROUND(AVG(kurs)) as avgKurs'))
        ->orderBy('created_at', 'asc')
        ->groupBy(DB::raw('year(created_at), month(created_at), day(created_at), type'))
        ->where('created_at', ">=", $dt);

      if (isset($place)) {
        $result = $result->where('place', '=', $place);
      }

      $result = $result->get();
      Cache::put($key, $result, 60);
    }
    return $result;
  }

  public function places() {
    return Kurs::select('place')
      ->where('place', '<>', '')
      ->distinct()
      ->get();
  }

}
