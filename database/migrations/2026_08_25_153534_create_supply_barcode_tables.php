<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_balances', function (Blueprint $table) {
            $table->string('master_barcode')->nullable()->unique()->after('quantity');
            $table->integer('latest_sequence')->default(0)->after('master_barcode');
            $table->boolean('has_pure_master_unit')->default(false)->after('latest_sequence');
        });

        Schema::create('inventory_balance_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_balance_id')->constrained('inventory_balances')->cascadeOnDelete();
            $table->foreignId('purchase_item_id')->nullable()->constrained('purchase_items')->nullOnDelete();
            $table->string('sub_barcode')->unique();
            $table->enum('status', ['available', 'used', 'disposed'])->default('available');
            $table->timestamps();
        });

        Schema::create('supply_master_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->integer('current_value')->default(0);
            $table->timestamps();
        });

        // Bootstrap legacy inventory balances
        \Illuminate\Support\Facades\DB::transaction(function () {
            $balances = \Illuminate\Support\Facades\DB::table('inventory_balances')->get();
            foreach ($balances as $balance) {
                // Generate a master barcode
                $seqRow = \Illuminate\Support\Facades\DB::table('supply_master_sequences')->where('name', 'master_barcode_seq')->first();
                if (!$seqRow) {
                    \Illuminate\Support\Facades\DB::table('supply_master_sequences')->insert(['name' => 'master_barcode_seq', 'current_value' => 0, 'created_at' => now(), 'updated_at' => now()]);
                    $seqRow = \Illuminate\Support\Facades\DB::table('supply_master_sequences')->where('name', 'master_barcode_seq')->first();
                }
                
                $sequence = $seqRow->current_value + 1;
                \Illuminate\Support\Facades\DB::table('supply_master_sequences')->where('name', 'master_barcode_seq')->update(['current_value' => $sequence]);
                $masterBarcode = sprintf('SUP-%05d', $sequence);
                
                $hasPureMaster = false;
                $latestSeq = 0;
                
                if ($balance->quantity > 0) {
                    $units = [];
                    for ($i = 1; $i <= $balance->quantity; $i++) {
                        $units[] = [
                            'inventory_balance_id' => $balance->id,
                            'sub_barcode' => sprintf('%s-%04d', $masterBarcode, $i),
                            'status' => 'available',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                    \Illuminate\Support\Facades\DB::table('inventory_balance_units')->insert($units);
                    $latestSeq = $balance->quantity;
                }
                
                \Illuminate\Support\Facades\DB::table('inventory_balances')
                    ->where('id', $balance->id)
                    ->update([
                        'master_barcode' => $masterBarcode,
                        'latest_sequence' => $latestSeq,
                        'has_pure_master_unit' => $hasPureMaster
                    ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supply_master_sequences');
        Schema::dropIfExists('inventory_balance_units');
        
        Schema::table('inventory_balances', function (Blueprint $table) {
            $table->dropColumn(['master_barcode', 'latest_sequence', 'has_pure_master_unit']);
        });
    }
};
