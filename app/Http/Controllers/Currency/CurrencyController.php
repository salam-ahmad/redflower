<?php

namespace App\Http\Controllers\Currency;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CurrencyController extends Controller
{
    public function index(): Response
    {
        $currencies = Currency::orderBy('name')->paginate(10);

        return Inertia::render('Currency/Index', [
            'currencies' => $currencies,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Currency/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:currencies,name',
            'is_active' => 'boolean',
        ]);

        Currency::create($validated);

        return redirect()->route('currencies.index')
            ->with('success', 'Currency created successfully.');
    }

    public function show(Currency $currency): Response
    {
        return Inertia::render('Currency/Show', [
            'currency' => $currency,
        ]);
    }

    public function edit(Currency $currency): Response
    {
        return Inertia::render('Currency/Edit', [
            'currency' => $currency,
        ]);
    }

    public function update(Request $request, Currency $currency)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:currencies,name,' . $currency->id,
            'is_active' => 'boolean',
        ]);

        $currency->update($validated);

        return redirect()->route('currencies.index')
            ->with('success', 'Currency updated successfully.');
    }
}
