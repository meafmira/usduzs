<?php

class KursController extends \BaseController {

	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */

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
		return [
			"buyS" => $this->getS('buy'),
			"sellS" => $this->getS('sell'),
			"buyAverage" => $this->getAverage('buy'),
			"sellAverage" => $this->getAverage('sell')
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
