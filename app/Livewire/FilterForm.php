<?php

namespace App\Livewire;

use Livewire\Component;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use App\Models\DataAlternatif;

class FilterForm extends Component implements HasForms
{
    use InteractsWithForms;

    public $filters = [
        'nama_komoditas' => null,
        'startDate' => null,
        'endDate' => null,
    ];

    // Property to hold komoditas options
    public $komoditasOptions = [];

    public function mount()
    {
        // Fetch unique komoditas options from the database
        $komoditasOptions = DataAlternatif::query()
            ->pluck('nama_komoditas')
            ->unique()
            ->filter()
            ->values()
            ->toArray();
        
        // Convert to associative array for select options
        $this->komoditasOptions = array_combine($komoditasOptions, $komoditasOptions);

        // Initialize default values
        $this->filters['startDate'] = now()->subMonth()->startOfMonth()->format('Y-m-d');
        $this->filters['endDate'] = now()->format('Y-m-d');
        
        // Fill form with default values
        $this->form->fill($this->filters);
        
        // Dispatch initial filter event
        $this->dispatch('filters-updated', $this->filters);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()
                    ->schema([
                        Select::make('nama_komoditas')
                            ->label('Nama Komoditas')
                            ->options($this->komoditasOptions)
                            ->placeholder('Pilih Komoditas')
                            ->live()
                            ->afterStateUpdated(fn () => $this->applyFilters())
                            ->columnSpan(1),
                        DatePicker::make('startDate')
                            ->label('Start Date')
                            ->default(now()->subMonth()->startOfMonth()->format('Y-m-d'))
                            ->live()
                            ->afterStateUpdated(fn () => $this->applyFilters()),
                        DatePicker::make('endDate')
                            ->label('End Date')
                            ->default(now()->format('Y-m-d'))
                            ->live()
                            ->afterStateUpdated(fn () => $this->applyFilters()),
                    ])
                    ->columns(3),
            ])
            ->statePath('filters');
    }

    public function applyFilters()
    {
        // Get current form state
        $formState = $this->form->getState();
        
        // Update filters property
        $this->filters = array_merge($this->filters, $formState);
        
        // Dispatch event with current filters
        $this->dispatch('filters-updated', $this->filters);
    }

    public function submit()
    {
        $this->applyFilters();
    }

    public function resetFilters()
    {
        $this->filters = [
            'nama_komoditas' => null,
            'startDate' => null,
            'endDate' => null,
        ];
        
        // Fill form with reset values
        $this->form->fill($this->filters);
        
        // Dispatch reset event
        $this->dispatch('filters-updated', $this->filters);
    }

    public function render()
    {
        return view('livewire.filter-form');
    }
}