<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('test_items', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->string('test_method')->nullable();
            $table->text('requirement')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('package_test_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('test_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['test_package_id', 'test_item_id']);
        });

        Schema::create('lab_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requestor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('test_package_id')->constrained()->restrictOnDelete();
            $table->string('pro_number')->nullable()->unique();
            $table->date('test_start_date')->nullable();
            $table->string('applicant');
            $table->string('brand');
            $table->text('sample_description');
            $table->string('season');
            $table->string('phase')->nullable();
            $table->string('style');
            $table->string('colour')->nullable();
            $table->string('product_type')->nullable();
            $table->string('po_number')->nullable();
            $table->text('destination')->nullable();
            $table->text('fiber_content')->nullable();
            $table->boolean('previous_test')->default(false);
            $table->string('previous_report_no')->nullable();
            $table->boolean('retest')->default(false);
            $table->text('previous_fail_test')->nullable();
            $table->string('previous_fail_report_no')->nullable();
            $table->string('function')->nullable();
            $table->text('care_instruction')->nullable();
            $table->date('issue_date')->nullable();
            $table->string('status', 32)->default('submitted')->index();
            $table->timestamps();
        });

        Schema::create('lab_request_test_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('test_item_id')->constrained()->restrictOnDelete();
            $table->boolean('selected')->default(true);
            $table->timestamps();
            $table->unique(['lab_request_id', 'test_item_id']);
        });

        Schema::create('specimens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_request_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('initial_quantity');
            $table->unsignedInteger('remaining_quantity')->nullable();
            $table->string('unit', 32)->default('pcs');
            $table->text('final_action')->nullable();
            $table->foreignId('handled_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('specimen_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by_id')->constrained('users')->restrictOnDelete();
            $table->string('phase', 16);
            $table->string('path');
            $table->text('remark')->nullable();
            $table->unsignedTinyInteger('sort_order')->default(1);
            $table->timestamps();
            $table->unique(['lab_request_id', 'phase', 'sort_order']);
        });

        Schema::create('cs_validations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_request_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('cs_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->date('estimated_completion_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('test_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_request_test_item_id')->unique()
                ->constrained('lab_request_test_items')->cascadeOnDelete();
            $table->foreignId('last_updated_by_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->string('result_status', 8)->nullable();
            $table->string('status', 16)->default('pending');
            $table->text('remark')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('test_item_details', function (Blueprint $table) {
            $table->bigIncrements('id_detail');
            $table->foreignId('test_result_id')->constrained()->cascadeOnDelete();
            $table->string('component_name');
            $table->json('measurements')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('final_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_request_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('validator_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at');
            $table->string('pdf_path')->nullable();
            $table->json('report_snapshot')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('final_reports');
        Schema::dropIfExists('test_item_details');
        Schema::dropIfExists('test_results');
        Schema::dropIfExists('cs_validations');
        Schema::dropIfExists('specimen_photos');
        Schema::dropIfExists('specimens');
        Schema::dropIfExists('lab_request_test_items');
        Schema::dropIfExists('lab_requests');
        Schema::dropIfExists('package_test_item');
        Schema::dropIfExists('test_items');
        Schema::dropIfExists('test_packages');
    }
};
