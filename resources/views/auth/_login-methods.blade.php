            @php $hasPublicPassword = (bool) $s['password_enabled']; @endphp

            @if (! $s['otp_enabled'] && ! $hasPublicPassword && ! $s['google_enabled'])
                <p class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">{{ __('loginpw.none') }}</p>
            @endif

            @if ($s['google_enabled'])
                {{-- Kept on this page: one tap opens Google's sign-in directly. --}}
                <div id="google-login" class="space-y-2"
                     data-api-key="{{ $fb['apiKey'] }}"
                     data-auth-domain="{{ $fb['authDomain'] }}"
                     data-project-id="{{ $fb['projectId'] }}"
                     data-verify-url="{{ route('login.google.verify') }}"
                     data-csrf="{{ csrf_token() }}"
                     data-text-signing="{{ __('glogin.status_signing') }}"
                     data-text-checking="{{ __('glogin.status_checking') }}"
                     data-text-error="{{ __('glogin.error') }}"
                     data-text-closed="{{ __('glogin.closed') }}"
                     data-text-banned="{{ __('glogin.banned') }}"
                     data-text-too-many="{{ __('glogin.too_many') }}"
                     data-text-not-ready="{{ __('glogin.not_ready') }}"
                     data-text-domain="{{ __('glogin.domain') }}"
                     data-text-provider="{{ __('glogin.provider') }}"
                     data-text-popup-blocked="{{ __('glogin.popup_blocked') }}"
                     data-text-network="{{ __('glogin.network') }}"
                     data-text-config="{{ __('glogin.config') }}"
                     data-text-account-exists="{{ __('glogin.account_exists') }}"
                     data-text-verification-failed="{{ __('glogin.verification_failed') }}">
                    <button type="button" id="gl-btn"
                            class="flex w-full items-center justify-center gap-3 rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-bold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60">
                        <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" aria-hidden="true"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-1 .67-2.28 1.07-3.71 1.07-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.67-.35-1.39-.35-2.09s.14-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
                        {{ __('glogin.button') }}
                    </button>
                    <p id="gl-status" class="min-h-5 text-center text-sm" aria-live="polite"></p>
                </div>
            @endif

            @if ($s['google_enabled'] && ($s['otp_enabled'] || $hasPublicPassword))
                <div class="my-6 flex items-center gap-3 text-xs font-medium uppercase tracking-wider text-slate-400">
                    <span class="h-px flex-1 bg-slate-200"></span>
                    <span>{{ __('glogin.or') }}</span>
                    <span class="h-px flex-1 bg-slate-200"></span>
                </div>
            @endif

            @if ($s['otp_enabled'])
                <section>
                    <h2 class="text-base font-bold text-slate-900">{{ __('login.phone_method') }}</h2>
                    <p class="mt-1 text-sm text-slate-600">{{ __('login.phone_hint') }}</p>

                    <form method="POST" action="{{ url('/login') }}" class="mt-4 space-y-3">
                        @csrf
                        <div>
                            <label for="phone" class="text-sm font-semibold text-slate-700">{{ __('login.phone') }}</label>
                            <input id="phone" name="phone" type="tel" inputmode="tel" autocomplete="tel"
                                   value="{{ old('phone') }}" placeholder="01XXXXXXXXX" required maxlength="30"
                                   class="mt-1.5 block w-full rounded-xl border border-slate-300 px-3 py-3 text-base shadow-sm outline-none transition focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100">
                            @error('phone')
                                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <button type="submit"
                                class="w-full rounded-xl bg-emerald-700 px-4 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2">
                            {{ __('login.send_code') }}
                        </button>
                    </form>
                </section>
            @endif

            @if ($hasPublicPassword)
                <details class="{{ $s['otp_enabled'] ? 'mt-5 border-t border-slate-200 pt-5' : 'mt-1' }} group">
                    <summary class="flex cursor-pointer list-none items-center justify-between rounded-xl bg-slate-50 px-3 py-3 text-sm font-bold text-slate-800 hover:bg-slate-100">
                        <span>{{ __('loginpw.or_password') }}</span>
                        <svg class="h-4 w-4 transition group-open:rotate-180" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd"/></svg>
                    </summary>
                    <form method="POST" action="{{ route('login.password') }}" class="mt-4 space-y-3">
                        @csrf
                        <div>
                            <label for="pw_phone" class="text-sm font-semibold text-slate-700">{{ __('login.phone') }}</label>
                            <input id="pw_phone" name="phone" type="tel" inputmode="tel" autocomplete="tel"
                                   value="{{ old('phone') }}" placeholder="01XXXXXXXXX" required maxlength="30"
                                   class="mt-1 block w-full rounded-xl border border-slate-300 px-3 py-2.5 text-base">
                        </div>
                        <div>
                            <label for="pw_password" class="text-sm font-semibold text-slate-700">{{ __('loginpw.password') }}</label>
                            <input id="pw_password" name="password" type="password" autocomplete="current-password" required maxlength="100"
                                   class="mt-1 block w-full rounded-xl border border-slate-300 px-3 py-2.5 text-base">
                            @error('pwlogin')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <button type="submit" class="w-full rounded-xl bg-slate-800 px-4 py-2.5 text-sm font-bold text-white hover:bg-slate-700">
                            {{ __('loginpw.login_btn') }}
                        </button>
                    </form>
                </details>

                @if ($s['registration_open'])
                    <details class="mt-3 group">
                        <summary class="flex cursor-pointer list-none items-center justify-between rounded-xl border border-slate-200 px-3 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                            <span>{{ __('loginpw.register_title') }}</span>
                            <svg class="h-4 w-4 transition group-open:rotate-180" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd"/></svg>
                        </summary>
                        <p class="mt-3 text-xs text-slate-500">{{ __('loginpw.register_hint') }}</p>
                        <form method="POST" action="{{ route('login.register') }}" class="mt-3 space-y-3">
                            @csrf
                            <div>
                                <label for="reg_phone" class="text-sm font-semibold text-slate-700">{{ __('login.phone') }}</label>
                                <input id="reg_phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" value="{{ old('phone') }}" placeholder="01XXXXXXXXX" required maxlength="30" class="mt-1 block w-full rounded-xl border border-slate-300 px-3 py-2.5 text-base">
                            </div>
                            <div>
                                <label for="reg_password" class="text-sm font-semibold text-slate-700">{{ __('loginpw.password') }}</label>
                                <input id="reg_password" name="password" type="password" autocomplete="new-password" required minlength="8" maxlength="100" class="mt-1 block w-full rounded-xl border border-slate-300 px-3 py-2.5 text-base">
                            </div>
                            <div>
                                <label for="reg_password2" class="text-sm font-semibold text-slate-700">{{ __('loginpw.password_confirm') }}</label>
                                <input id="reg_password2" name="password_confirmation" type="password" autocomplete="new-password" required minlength="8" maxlength="100" class="mt-1 block w-full rounded-xl border border-slate-300 px-3 py-2.5 text-base">
                                @error('register')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <button type="submit" class="w-full rounded-xl bg-slate-800 px-4 py-2.5 text-sm font-bold text-white hover:bg-slate-700">{{ __('loginpw.register_btn') }}</button>
                        </form>
                    </details>
                @endif
            @endif

            @if ($s['otp_enabled'])
                <p class="mt-5 text-center text-xs leading-5 text-slate-500">{{ __('login.privacy') }}</p>
            @endif
