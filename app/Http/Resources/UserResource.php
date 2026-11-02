<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $company = $this->company;
        $plan = $company?->plan;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'username' => $this->username,
            'phone' => $this->phone,
            'role' => $this->role,
            'is_active' => (bool) $this->is_active,
            'company_id' => $this->company_id,
            'company_code' => $company?->company_code,
            'company_name' => $company?->name,
            'company_logo' => $company?->logo_url,
            'plan' => $plan ? [
                'id' => $plan->id,
                'name' => $plan->name,
                'description' => $plan->description,
                'price' => (float) $plan->price,
                'duration' => $plan->duration,
                'max_users' => (int) $plan->max_users,
                'status' => $plan->status,
                'expires_at' => $company?->expires_at?->format('Y-m-d'),
            ] : null,
            'plan_expires_at' => $company?->expires_at?->format('Y-m-d'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}