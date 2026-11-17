<?php

namespace App\Http\Resources;

use Carbon\Carbon;
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

        $isExpired = false;
        $planDaysRemaining = null;
        $isNearExpiry = false;

        if ($company && ! $this->isSuperAdmin()) {
            if ($company->status === 'inactive') {
                $isExpired = true;
                $planDaysRemaining = -1;
            } elseif (! empty($company->expires_at)) {
                $endOfDay = Carbon::parse($company->expires_at, 'Asia/Kolkata')->endOfDay();
                if ($endOfDay->isPast()) {
                    $isExpired = true;
                    $planDaysRemaining = -1;
                } else {
                    $now = Carbon::now('Asia/Kolkata')->startOfDay();
                    $startOfExpiryDay = Carbon::parse($company->expires_at, 'Asia/Kolkata')->startOfDay();
                    $diffInDays = (int) $now->diffInDays($startOfExpiryDay, false);
                    $planDaysRemaining = max(0, $diffInDays);
                    if ($diffInDays <= 2) {
                        $isNearExpiry = true;
                    }
                }
            }
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'username' => $this->username,
            'phone' => $this->phone,
            'profile_image' => $this->profile_image_url ?? $this->profile_image,
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
            'is_plan_expired' => $isExpired,
            'plan_days_remaining' => $planDaysRemaining,
            'is_near_expiry' => $isNearExpiry,
            'plan_expires_at' => $company?->expires_at?->format('Y-m-d'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}