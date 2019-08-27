<?

public function orderCoupon(Request $request) {
if (!$request->user_id) 
	{
	return $this->errorResponse("user id missing");
	}

if (!$request->code)
	{
	return $this->errorResponse("Code missing");
	}

if (!$request->amount)
	{
	return $this->errorResponse("Amount missing");
	}

$couponApplied = CouponTransaction::where(["user_id" => $request->user_id, "status" => 0])->first();

if ($couponApplied) 
	{
	return $this->errorResponse("Coupon already applied on this cart.");
	}

$coupon = Coupon::where('code', $request->code)->whereDate('valid_to', '>=', date('Y-m-d'))->first();

if (!$coupon)
	{
	return $this->errorResponse("Sorry!! Coupon invalid or Expired.");
	}

if ($coupon->quantity == 0) 
	{
	return $this->errorResponse("No More coupon available.");
	}

$cartItems = Cart::with(['product' => function ($query) 
		{
			$query->select('id', 'price', 'discount');
		}
		])->where("user_id", $request->user_id)->get();

if (!$cartItems)
	{
	return $this->errorResponse("Cart is empty.");
	}

$cartTotal = 0;
foreach ($cartItems as $cartItem)
	{
			$totalPrice = round(((int) $cartItem->quantity * (float) $cartItem->product->price), 2);
			$discountedPrice = round(((($cartItem->product->discount / 100) (float) $cartItem->product->price) (int) $cartItem->quantity), 2);
			$actualPrice = round(((float) $totalPrice - (float) $discountedPrice), 2);
			$cartTotal += round($actualPrice, 2);
	}

if ($cartTotal < $coupon->min_cart_amount) 
	{
		$this->errorResponse("Minimum cart amount should be " . $coupon->min_cart_amount);
	}

$taxPrice = round((((float) $cartTotal * 2) / 100), 2);

if ($coupon->type == 1) 
{
	$discount = round((($coupon->amount / 100) * (float) $cartTotal), 2);

	if ($discount > $coupon->max_less_amount)
	{
		$discount = $coupon->max_less_amount;
	}

		if($discount >= $cartTotal)
		{
			$discount = $cartTotal;
		}

			$trans = new CouponTransaction();
			$trans->user_id = $request->user_id;
			$trans->coupon_id = $coupon->id;
			$trans->coupon_code = $coupon->code;
			$trans->discount = $discount;
			$trans->order_id = 0;
			$trans->status = 0;
			$trans->save();

			$data['cart'] = [
			'cart_total' => $cartTotal,
			'coupon_id' => $coupon->id,
			'discount' => (float) round($discount,2),
			'tax' => (float) round($taxPrice,2),
			'payable_amount' => round((float) $request->amount - (float) $discount, 2),
			'coupon_trans_id' => $trans->id,
			];
			return $this->successResponse("Cart item list.", $data);
}


if ($coupon->type == 2) 
	{
		$trans = new CouponTransaction();
		$trans->user_id = $request->user_id;
		$trans->coupon_id = $coupon->id;
		$trans->coupon_code = $coupon->code;
		$discount = $coupon->amount;
		$trans->discount = $discount;
		$trans->order_id = 0;
		$trans->status = 0;
		$trans->save();

		if ($discount > $coupon->max_less_amount)
		{
			$discount = $coupon->max_less_amount;
		}
		$discount = round($discount, 2);
		
			if($discount > $cartTotal)
			{
				$discount = $cartTotal;
			}
			
			$data['cart'] = [
			'cart_total' => $cartTotal,
			'coupon_id' => $coupon->id,
			'discount' => (float) $discount,
			'tax' => (float) $taxPrice,
			'payable_amount' => ((float) $request->amount - (float) $discount),
			'coupon_trans_id' => $trans->id,
			];
			return $this->successResponse("Cart item list.", $data);
	}
}