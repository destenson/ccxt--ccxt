<?php

namespace ccxt;

class poloniex extends Exchange {

    public function describe () {
        return array_replace_recursive (parent::describe (), array (
            'id' => 'poloniex',
            'name' => 'Poloniex',
            'countries' => 'US',
            'rateLimit' => 1000, // up to 6 calls per second
            'hasCORS' => false,
            // obsolete metainfo interface
            'hasFetchMyTrades' => true,
            'hasFetchOrder' => true,
            'hasFetchOrders' => true,
            'hasFetchOpenOrders' => true,
            'hasFetchClosedOrders' => true,
            'hasFetchTickers' => true,
            'hasFetchCurrencies' => true,
            'hasWithdraw' => true,
            'hasFetchOHLCV' => true,
            // new metainfo interface
            'has' => array (
                'createDepositAddress' => true,
                'fetchDepositAddress' => true,
                'fetchOHLCV' => true,
                'fetchMyTrades' => true,
                'fetchOrder' => 'emulated',
                'fetchOrders' => 'emulated',
                'fetchOpenOrders' => true,
                'fetchClosedOrders' => 'emulated',
                'fetchTickers' => true,
                'fetchCurrencies' => true,
                'withdraw' => true,
            ),
            'timeframes' => array (
                '5m' => 300,
                '15m' => 900,
                '30m' => 1800,
                '2h' => 7200,
                '4h' => 14400,
                '1d' => 86400,
            ),
            'urls' => array (
                'logo' => 'https://user-images.githubusercontent.com/1294454/27766817-e9456312-5ee6-11e7-9b3c-b628ca5626a5.jpg',
                'api' => array (
                    'public' => 'https://poloniex.com/public',
                    'private' => 'https://poloniex.com/tradingApi',
                ),
                'www' => 'https://poloniex.com',
                'doc' => array (
                    'https://poloniex.com/support/api/',
                    'http://pastebin.com/dMX7mZE0',
                ),
                'fees' => 'https://poloniex.com/fees',
            ),
            'api' => array (
                'public' => array (
                    'get' => array (
                        'return24hVolume',
                        'returnChartData',
                        'returnCurrencies',
                        'returnLoanOrders',
                        'returnOrderBook',
                        'returnTicker',
                        'returnTradeHistory',
                    ),
                ),
                'private' => array (
                    'post' => array (
                        'buy',
                        'cancelLoanOffer',
                        'cancelOrder',
                        'closeMarginPosition',
                        'createLoanOffer',
                        'generateNewAddress',
                        'getMarginPosition',
                        'marginBuy',
                        'marginSell',
                        'moveOrder',
                        'returnActiveLoans',
                        'returnAvailableAccountBalances',
                        'returnBalances',
                        'returnCompleteBalances',
                        'returnDepositAddresses',
                        'returnDepositsWithdrawals',
                        'returnFeeInfo',
                        'returnLendingHistory',
                        'returnMarginAccountSummary',
                        'returnOpenLoanOffers',
                        'returnOpenOrders',
                        'returnOrderTrades',
                        'returnTradableBalances',
                        'returnTradeHistory',
                        'sell',
                        'toggleAutoRenew',
                        'transferBalance',
                        'withdraw',
                    ),
                ),
            ),
            'fees' => array (
                'trading' => array (
                    'maker' => 0.0015,
                    'taker' => 0.0025,
                ),
                'funding' => 0.0,
            ),
            'limits' => array (
                'amount' => array (
                    'min' => 0.00000001,
                    'max' => 1000000000,
                ),
                'price' => array (
                    'min' => 0.00000001,
                    'max' => 1000000000,
                ),
                'cost' => array (
                    'min' => 0.00000000,
                    'max' => 1000000000,
                ),
            ),
            'precision' => array (
                'amount' => 8,
                'price' => 8,
            ),
        ));
    }

    public function calculate_fee ($symbol, $type, $side, $amount, $price, $takerOrMaker = 'taker', $params = array ()) {
        $market = $this->markets[$symbol];
        $key = 'quote';
        $rate = $market[$takerOrMaker];
        $cost = floatval ($this->cost_to_precision($symbol, $amount * $rate));
        if ($side === 'sell') {
            $cost *= $price;
        } else {
            $key = 'base';
        }
        return array (
            'type' => $takerOrMaker,
            'currency' => $market[$key],
            'rate' => $rate,
            'cost' => floatval ($this->fee_to_precision($symbol, $cost)),
        );
    }

    public function common_currency_code ($currency) {
        if ($currency === 'BTM')
            return 'Bitmark';
        if ($currency === 'STR')
            return 'XLM';
        return $currency;
    }

    public function currency_id ($currency) {
        if ($currency === 'Bitmark')
            return 'BTM';
        if ($currency === 'XLM')
            return 'STR';
        return $currency;
    }

    public function parse_ohlcv ($ohlcv, $market = null, $timeframe = '5m', $since = null, $limit = null) {
        return [
            $ohlcv['date'] * 1000,
            $ohlcv['open'],
            $ohlcv['high'],
            $ohlcv['low'],
            $ohlcv['close'],
            $ohlcv['volume'],
        ];
    }

    public function fetch_ohlcv ($symbol, $timeframe = '5m', $since = null, $limit = null, $params = array ()) {
        $this->load_markets();
        $market = $this->market ($symbol);
        if (!$since)
            $since = 0;
        $request = array (
            'currencyPair' => $market['id'],
            'period' => $this->timeframes[$timeframe],
            'start' => intval ($since / 1000),
        );
        if ($limit)
            $request['end'] = $this->sum ($request['start'], $limit * $this->timeframes[$timeframe]);
        $response = $this->publicGetReturnChartData (array_merge ($request, $params));
        return $this->parse_ohlcvs($response, $market, $timeframe, $since, $limit);
    }

    public function fetch_markets () {
        $markets = $this->publicGetReturnTicker ();
        $keys = is_array ($markets) ? array_keys ($markets) : array ();
        $result = array ();
        for ($p = 0; $p < count ($keys); $p++) {
            $id = $keys[$p];
            $market = $markets[$id];
            list ($quote, $base) = explode ('_', $id);
            $base = $this->common_currency_code($base);
            $quote = $this->common_currency_code($quote);
            $symbol = $base . '/' . $quote;
            $result[] = array_merge ($this->fees['trading'], array (
                'id' => $id,
                'symbol' => $symbol,
                'base' => $base,
                'quote' => $quote,
                'active' => true,
                'lot' => $this->limits['amount']['min'],
                'info' => $market,
            ));
        }
        return $result;
    }

    public function fetch_balance ($params = array ()) {
        $this->load_markets();
        $balances = $this->privatePostReturnCompleteBalances (array_merge (array (
            'account' => 'all',
        ), $params));
        $result = array ( 'info' => $balances );
        $currencies = is_array ($balances) ? array_keys ($balances) : array ();
        for ($c = 0; $c < count ($currencies); $c++) {
            $id = $currencies[$c];
            $balance = $balances[$id];
            $currency = $this->common_currency_code($id);
            $account = array (
                'free' => floatval ($balance['available']),
                'used' => floatval ($balance['onOrders']),
                'total' => 0.0,
            );
            $account['total'] = $this->sum ($account['free'], $account['used']);
            $result[$currency] = $account;
        }
        return $this->parse_balance($result);
    }

    public function fetch_fees ($params = array ()) {
        $this->load_markets();
        $fees = $this->privatePostReturnFeeInfo ();
        return array (
            'info' => $fees,
            'maker' => floatval ($fees['makerFee']),
            'taker' => floatval ($fees['takerFee']),
            'withdraw' => 0.0,
        );
    }

    public function fetch_order_book ($symbol, $params = array ()) {
        $this->load_markets();
        $orderbook = $this->publicGetReturnOrderBook (array_merge (array (
            'currencyPair' => $this->market_id($symbol),
            // 'depth' => 100,
        ), $params));
        return $this->parse_order_book($orderbook);
    }

    public function parse_ticker ($ticker, $market = null) {
        $timestamp = $this->milliseconds ();
        $symbol = null;
        if ($market)
            $symbol = $market['symbol'];
        return array (
            'symbol' => $symbol,
            'timestamp' => $timestamp,
            'datetime' => $this->iso8601 ($timestamp),
            'high' => floatval ($ticker['high24hr']),
            'low' => floatval ($ticker['low24hr']),
            'bid' => floatval ($ticker['highestBid']),
            'ask' => floatval ($ticker['lowestAsk']),
            'vwap' => null,
            'open' => null,
            'close' => null,
            'first' => null,
            'last' => floatval ($ticker['last']),
            'change' => floatval ($ticker['percentChange']),
            'percentage' => null,
            'average' => null,
            'baseVolume' => floatval ($ticker['quoteVolume']),
            'quoteVolume' => floatval ($ticker['baseVolume']),
            'info' => $ticker,
        );
    }

    public function fetch_tickers ($symbols = null, $params = array ()) {
        $this->load_markets();
        $tickers = $this->publicGetReturnTicker ($params);
        $ids = is_array ($tickers) ? array_keys ($tickers) : array ();
        $result = array ();
        for ($i = 0; $i < count ($ids); $i++) {
            $id = $ids[$i];
            $market = $this->markets_by_id[$id];
            $symbol = $market['symbol'];
            $ticker = $tickers[$id];
            $result[$symbol] = $this->parse_ticker($ticker, $market);
        }
        return $result;
    }

    public function fetch_currencies ($params = array ()) {
        $currencies = $this->publicGetReturnCurrencies ($params);
        $ids = is_array ($currencies) ? array_keys ($currencies) : array ();
        $result = array ();
        for ($i = 0; $i < count ($ids); $i++) {
            $id = $ids[$i];
            $currency = $currencies[$id];
            // todo => will need to rethink the fees
            // to add support for multiple withdrawal/deposit methods and
            // differentiated fees for each particular method
            $precision = 8; // default $precision, todo => fix "magic constants"
            $code = $this->common_currency_code($id);
            $active = ($currency['delisted'] === 0);
            $status = ($currency['disabled']) ? 'disabled' : 'ok';
            if ($status !== 'ok')
                $active = false;
            $result[$code] = array (
                'id' => $id,
                'code' => $code,
                'info' => $currency,
                'name' => $currency['name'],
                'active' => $active,
                'status' => $status,
                'fee' => $currency['txFee'], // todo => redesign
                'precision' => $precision,
                'limits' => array (
                    'amount' => array (
                        'min' => pow (10, -$precision),
                        'max' => pow (10, $precision),
                    ),
                    'price' => array (
                        'min' => pow (10, -$precision),
                        'max' => pow (10, $precision),
                    ),
                    'cost' => array (
                        'min' => null,
                        'max' => null,
                    ),
                    'withdraw' => array (
                        'min' => $currency['txFee'],
                        'max' => pow (10, $precision),
                    ),
                ),
            );
        }
        return $result;
    }

    public function fetch_ticker ($symbol, $params = array ()) {
        $this->load_markets();
        $market = $this->market ($symbol);
        $tickers = $this->publicGetReturnTicker ($params);
        $ticker = $tickers[$market['id']];
        return $this->parse_ticker($ticker, $market);
    }

    public function parse_trade ($trade, $market = null) {
        $timestamp = $this->parse8601 ($trade['date']);
        $symbol = null;
        $base = null;
        $quote = null;
        if ((!$market) && (is_array ($trade) && array_key_exists ('currencyPair', $trade))) {
            $currencyPair = $trade['currencyPair'];
            if (is_array ($this->markets_by_id) && array_key_exists ($currencyPair, $this->markets_by_id)) {
                $market = $this->markets_by_id[$currencyPair];
            } else {
                $parts = explode ('_', $currencyPair);
                $quote = $parts[0];
                $base = $parts[1];
                $symbol = $base . '/' . $quote;
            }
        }
        if ($market) {
            $symbol = $market['symbol'];
            $base = $market['base'];
            $quote = $market['quote'];
        }
        $side = $trade['type'];
        $fee = null;
        $cost = $this->safe_float($trade, 'total');
        $amount = floatval ($trade['amount']);
        if (is_array ($trade) && array_key_exists ('fee', $trade)) {
            $rate = floatval ($trade['fee']);
            $feeCost = null;
            $currency = null;
            if ($side === 'buy') {
                $currency = $base;
                $feeCost = $amount * $rate;
            } else {
                $currency = $quote;
                if ($cost !== null)
                    $feeCost = $cost * $rate;
            }
            $fee = array (
                'type' => null,
                'rate' => $rate,
                'cost' => $feeCost,
                'currency' => $currency,
            );
        }
        return array (
            'info' => $trade,
            'timestamp' => $timestamp,
            'datetime' => $this->iso8601 ($timestamp),
            'symbol' => $symbol,
            'id' => $this->safe_string($trade, 'tradeID'),
            'order' => $this->safe_string($trade, 'orderNumber'),
            'type' => 'limit',
            'side' => $side,
            'price' => floatval ($trade['rate']),
            'amount' => $amount,
            'cost' => $cost,
            'fee' => $fee,
        );
    }

    public function fetch_trades ($symbol, $since = null, $limit = null, $params = array ()) {
        $this->load_markets();
        $market = $this->market ($symbol);
        $request = array (
            'currencyPair' => $market['id'],
        );
        if ($since) {
            $request['start'] = intval ($since / 1000);
            $request['end'] = $this->seconds (); // last 50000 $trades by default
        }
        $trades = $this->publicGetReturnTradeHistory (array_merge ($request, $params));
        return $this->parse_trades($trades, $market, $since, $limit);
    }

    public function fetch_my_trades ($symbol = null, $since = null, $limit = null, $params = array ()) {
        $this->load_markets();
        $market = null;
        if ($symbol)
            $market = $this->market ($symbol);
        $pair = $market ? $market['id'] : 'all';
        $request = array ( 'currencyPair' => $pair );
        if ($since) {
            $request['start'] = intval ($since / 1000);
            $request['end'] = $this->seconds ();
        }
        // $limit is disabled (does not really work as expected)
        // if ($limit)
        //     $request['limit'] = intval ($limit);
        $response = $this->privatePostReturnTradeHistory (array_merge ($request, $params));
        $result = array ();
        if ($market) {
            $result = $this->parse_trades($response, $market);
        } else {
            if ($response) {
                $ids = is_array ($response) ? array_keys ($response) : array ();
                for ($i = 0; $i < count ($ids); $i++) {
                    $id = $ids[$i];
                    $market = null;
                    if (is_array ($this->markets_by_id) && array_key_exists ($id, $this->markets_by_id))
                        $market = $this->markets_by_id[$id];
                    $trades = $this->parse_trades($response[$id], $market);
                    for ($j = 0; $j < count ($trades); $j++) {
                        $result[] = $trades[$j];
                    }
                }
            }
        }
        return $this->filter_by_since_limit($result, $since, $limit);
    }

    public function parse_order ($order, $market = null) {
        $timestamp = $this->safe_integer($order, 'timestamp');
        if (!$timestamp)
            $timestamp = $this->parse8601 ($order['date']);
        $trades = null;
        if (is_array ($order) && array_key_exists ('resultingTrades', $order))
            $trades = $this->parse_trades($order['resultingTrades'], $market);
        $symbol = null;
        if ($market)
            $symbol = $market['symbol'];
        $price = $this->safe_float($order, 'price');
        $cost = $this->safe_float($order, 'total', 0.0);
        $remaining = $this->safe_float($order, 'amount');
        $amount = $this->safe_float($order, 'startingAmount', $remaining);
        $filled = null;
        if ($amount !== null) {
            if ($remaining !== null)
                $filled = $amount - $remaining;
        }
        if ($filled === null) {
            if ($trades !== null) {
                $filled = 0;
                $cost = 0;
                for ($i = 0; $i < count ($trades); $i++) {
                    $trade = $trades[$i];
                    $tradeAmount = $trade['amount'];
                    $tradePrice = $trade['price'];
                    $filled = $this->sum ($filled, $tradeAmount);
                    $cost .= $tradePrice * $tradeAmount;
                }
            }
        }
        return array (
            'info' => $order,
            'id' => $order['orderNumber'],
            'timestamp' => $timestamp,
            'datetime' => $this->iso8601 ($timestamp),
            'status' => $order['status'],
            'symbol' => $symbol,
            'type' => $order['type'],
            'side' => $order['side'],
            'price' => $price,
            'cost' => $cost,
            'amount' => $amount,
            'filled' => $filled,
            'remaining' => $remaining,
            'trades' => $trades,
            'fee' => null,
        );
    }

    public function parse_open_orders ($orders, $market, $result = []) {
        for ($i = 0; $i < count ($orders); $i++) {
            $order = $orders[$i];
            $extended = array_merge ($order, array (
                'status' => 'open',
                'type' => 'limit',
                'side' => $order['type'],
                'price' => $order['rate'],
            ));
            $result[] = $this->parse_order($extended, $market);
        }
        return $result;
    }

    public function fetch_orders ($symbol = null, $since = null, $limit = null, $params = array ()) {
        $this->load_markets();
        $market = null;
        if ($symbol)
            $market = $this->market ($symbol);
        $pair = $market ? $market['id'] : 'all';
        $response = $this->privatePostReturnOpenOrders (array_merge (array (
            'currencyPair' => $pair,
        )));
        $openOrders = array ();
        if ($market) {
            $openOrders = $this->parse_open_orders ($response, $market, $openOrders);
        } else {
            $marketIds = is_array ($response) ? array_keys ($response) : array ();
            for ($i = 0; $i < count ($marketIds); $i++) {
                $marketId = $marketIds[$i];
                $orders = $response[$marketId];
                $m = $this->markets_by_id[$marketId];
                $openOrders = $this->parse_open_orders ($orders, $m, $openOrders);
            }
        }
        for ($j = 0; $j < count ($openOrders); $j++) {
            $this->orders[$openOrders[$j]['id']] = $openOrders[$j];
        }
        $openOrdersIndexedById = $this->index_by($openOrders, 'id');
        $cachedOrderIds = is_array ($this->orders) ? array_keys ($this->orders) : array ();
        $result = array ();
        for ($k = 0; $k < count ($cachedOrderIds); $k++) {
            $id = $cachedOrderIds[$k];
            if (is_array ($openOrdersIndexedById) && array_key_exists ($id, $openOrdersIndexedById)) {
                $this->orders[$id] = array_merge ($this->orders[$id], $openOrdersIndexedById[$id]);
            } else {
                $order = $this->orders[$id];
                if ($order['status'] === 'open') {
                    $this->orders[$id] = array_merge ($order, array (
                        'status' => 'closed',
                        'cost' => $order['amount'] * $order['price'],
                        'filled' => $order['amount'],
                        'remaining' => 0.0,
                    ));
                }
            }
            $order = $this->orders[$id];
            if ($market) {
                if ($order['symbol'] === $symbol)
                    $result[] = $order;
            } else {
                $result[] = $order;
            }
        }
        return $this->filter_by_since_limit($result, $since, $limit);
    }

    public function fetch_order ($id, $symbol = null, $params = array ()) {
        $since = $this->safe_value($params, 'since');
        $limit = $this->safe_value($params, 'limit');
        $request = $this->omit ($params, array ( 'since', 'limit' ));
        $orders = $this->fetch_orders($symbol, $since, $limit, $request);
        for ($i = 0; $i < count ($orders); $i++) {
            if ($orders[$i]['id'] === $id)
                return $orders[$i];
        }
        throw new OrderNotCached ($this->id . ' order $id ' . (string) $id . ' not found in cache');
    }

    public function filter_orders_by_status ($orders, $status) {
        $result = array ();
        for ($i = 0; $i < count ($orders); $i++) {
            if ($orders[$i]['status'] === $status)
                $result[] = $orders[$i];
        }
        return $result;
    }

    public function fetch_open_orders ($symbol = null, $since = null, $limit = null, $params = array ()) {
        $orders = $this->fetch_orders($symbol, $since, $limit, $params);
        return $this->filter_orders_by_status ($orders, 'open');
    }

    public function fetch_closed_orders ($symbol = null, $since = null, $limit = null, $params = array ()) {
        $orders = $this->fetch_orders($symbol, $since, $limit, $params);
        return $this->filter_orders_by_status ($orders, 'closed');
    }

    public function create_order ($symbol, $type, $side, $amount, $price = null, $params = array ()) {
        if ($type === 'market')
            throw new ExchangeError ($this->id . ' allows limit orders only');
        $this->load_markets();
        $method = 'privatePost' . $this->capitalize ($side);
        $market = $this->market ($symbol);
        $price = floatval ($price);
        $amount = floatval ($amount);
        $response = $this->$method (array_merge (array (
            'currencyPair' => $market['id'],
            'rate' => $this->price_to_precision($symbol, $price),
            'amount' => $this->amount_to_precision($symbol, $amount),
        ), $params));
        $timestamp = $this->milliseconds ();
        $order = $this->parse_order(array_merge (array (
            'timestamp' => $timestamp,
            'status' => 'open',
            'type' => $type,
            'side' => $side,
            'price' => $price,
            'amount' => $amount,
        ), $response), $market);
        $id = $order['id'];
        $this->orders[$id] = $order;
        return array_merge (array ( 'info' => $response ), $order);
    }

    public function edit_order ($id, $symbol, $type, $side, $amount, $price = null, $params = array ()) {
        $this->load_markets();
        $price = floatval ($price);
        $request = array (
            'orderNumber' => $id,
            'rate' => $this->price_to_precision($symbol, $price),
        );
        if ($amount !== null) {
            $amount = floatval ($amount);
            $request['amount'] = $this->amount_to_precision($symbol, $amount);
        }
        $response = $this->privatePostMoveOrder (array_merge ($request, $params));
        $result = null;
        if (is_array ($this->orders) && array_key_exists ($id, $this->orders)) {
            $this->orders[$id]['status'] = 'canceled';
            $newid = $response['orderNumber'];
            $this->orders[$newid] = array_merge ($this->orders[$id], array (
                'id' => $newid,
                'price' => $price,
                'status' => 'open',
            ));
            if ($amount !== null)
                $this->orders[$newid]['amount'] = $amount;
            $result = array_merge ($this->orders[$newid], array ( 'info' => $response ));
        } else {
            $market = null;
            if ($symbol)
                $market = $this->market ($symbol);
            $result = $this->parse_order($response, $market);
            $this->orders[$result['id']] = $result;
        }
        return $result;
    }

    public function cancel_order ($id, $symbol = null, $params = array ()) {
        $this->load_markets();
        $response = null;
        try {
            $response = $this->privatePostCancelOrder (array_merge (array (
                'orderNumber' => $id,
            ), $params));
            if (is_array ($this->orders) && array_key_exists ($id, $this->orders))
                $this->orders[$id]['status'] = 'canceled';
        } catch (Exception $e) {
            if ($this->last_http_response) {
                if (mb_strpos ($this->last_http_response, 'Invalid order') !== false)
                    throw new OrderNotFound ($this->id . ' cancelOrder() error => ' . $this->last_http_response);
            }
            throw $e;
        }
        return $response;
    }

    public function fetch_order_status ($id, $symbol = null) {
        $this->load_markets();
        $orders = $this->fetch_open_orders($symbol);
        $indexed = $this->index_by($orders, 'id');
        return (is_array ($indexed) && array_key_exists ($id, $indexed)) ? 'open' : 'closed';
    }

    public function fetch_order_trades ($id, $symbol = null, $params = array ()) {
        $this->load_markets();
        $trades = $this->privatePostReturnOrderTrades (array_merge (array (
            'orderNumber' => $id,
        ), $params));
        return $this->parse_trades($trades);
    }

    public function create_deposit_address ($currency, $params = array ()) {
        $currencyId = $this->currency_id ($currency);
        $response = $this->privatePostGenerateNewAddress (array (
            'currency' => $currencyId,
        ));
        $address = null;
        if ($response['success'] === 1)
            $address = $this->safe_string($response, 'response');
        if (!$address)
            throw new ExchangeError ($this->id . ' createDepositAddress failed => ' . $this->last_http_response);
        return array (
            'currency' => $currency,
            'address' => $address,
            'status' => 'ok',
            'info' => $response,
        );
    }

    public function fetch_deposit_address ($currency, $params = array ()) {
        $response = $this->privatePostReturnDepositAddresses ();
        $currencyId = $this->currency_id ($currency);
        $address = $this->safe_string($response, $currencyId);
        $status = $address ? 'ok' : 'none';
        return array (
            'currency' => $currency,
            'address' => $address,
            'status' => $status,
            'info' => $response,
        );
    }

    public function withdraw ($currency, $amount, $address, $tag = null, $params = array ()) {
        $this->load_markets();
        $currencyId = $this->currency_id ($currency);
        $request = array (
            'currency' => $currencyId,
            'amount' => $amount,
            'address' => $address,
        );
        if ($tag)
            $request['paymentId'] = $tag;
        $result = $this->privatePostWithdraw (array_merge ($request, $params));
        return array (
            'info' => $result,
            'id' => $result['response'],
        );
    }

    public function nonce () {
        return $this->milliseconds ();
    }

    public function sign ($path, $api = 'public', $method = 'GET', $params = array (), $headers = null, $body = null) {
        $url = $this->urls['api'][$api];
        $query = array_merge (array ( 'command' => $path ), $params);
        if ($api === 'public') {
            $url .= '?' . $this->urlencode ($query);
        } else {
            $this->check_required_credentials();
            $query['nonce'] = $this->nonce ();
            $body = $this->urlencode ($query);
            $headers = array (
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Key' => $this->apiKey,
                'Sign' => $this->hmac ($this->encode ($body), $this->encode ($this->secret), 'sha512'),
            );
        }
        return array ( 'url' => $url, 'method' => $method, 'body' => $body, 'headers' => $headers );
    }

    public function handle_errors ($code, $reason, $url, $method, $headers, $body) {
        if ($code >= 400) {
            if ($body[0] === '{') {
                $response = json_decode ($body, $as_associative_array = true);
                if (is_array ($response) && array_key_exists ('error', $response)) {
                    $error = $this->id . ' ' . $body;
                    if (mb_strpos ($response['error'], 'Total must be at least') !== false) {
                        throw new InvalidOrder ($error);
                    } else if (mb_strpos ($response['error'], 'Not enough') !== false) {
                        throw new InsufficientFunds ($error);
                    } else if (mb_strpos ($response['error'], 'Nonce must be greater') !== false) {
                        throw new ExchangeNotAvailable ($error);
                    }
                }
            }
        }
    }

    public function request ($path, $api = 'public', $method = 'GET', $params = array (), $headers = null, $body = null) {
        $response = $this->fetch2 ($path, $api, $method, $params, $headers, $body);
        if (is_array ($response) && array_key_exists ('error', $response)) {
            $error = $this->id . ' ' . $this->json ($response);
            $failed = mb_strpos ($response['error'], 'Not enough') !== false;
            if ($failed)
                throw new InsufficientFunds ($error);
            throw new ExchangeError ($error);
        }
        return $response;
    }
}
