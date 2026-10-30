<script setup lang="ts">
withDefaults(defineProps<{ mode?: 'register' | 'login' }>(), { mode: 'register' });
useHead({
  link: [{ rel: 'stylesheet', href: 'https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;650;700&family=Manrope:wght@400;500;600;700&display=swap' }]
});
</script>

<template>
  <div class="registration-shell" :class="{ 'login-shell': mode === 'login' }">
    <header class="registration-header">
      <a href="/" class="registration-brand" aria-label="Identifica Trânsito — início">
        <span class="registration-logo"><img src="/images/registration/logo.webp" alt="" /></span>
        <span>identifica<strong>trânsito<span>.</span></strong></span>
      </a>
      <a href="/" class="registration-back">Voltar ao site <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 19 19 5M5 5h14v14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" /></svg></a>
    </header>
    <main class="registration-layout">
      <aside class="registration-story" aria-labelledby="registration-story-title">
        <img class="registration-photo" src="/images/registration/motorista.webp" alt="Motorista com a etiqueta vermelha do Identifica Trânsito no para-brisa" />
        <div class="registration-story-copy">
          <span class="registration-eyebrow">UM SINAL DE CUIDADO</span>
          <h2 v-if="mode === 'register'" id="registration-story-title">Uma tag no seu carro.<br />Mais respeito<br />no seu caminho.</h2>
          <h2 v-else id="registration-story-title">Seu caminho.<br />Nossa comunidade.<br />Mais cuidado.</h2>
          <p>{{ mode === 'login' ? 'Acompanhe seus veículos e etiquetas. Continue fazendo parte de um trânsito mais humano.' : 'Faça parte de uma ideia que aproxima informação, empatia e cuidado no trânsito.' }}</p>
          <div class="registration-palette" aria-label="As cinco cores do projeto"><i></i><i></i><i></i><i></i><i></i></div>
          <span class="registration-caption">CINCO CORES. MUITAS HISTÓRIAS.</span>
        </div>
      </aside>
      <section class="registration-form-panel" :aria-label="mode === 'login' ? 'Entrar na sua conta' : 'Criar conta'">
        <nav class="registration-tabs" aria-label="Acesso à plataforma">
          <template v-if="mode === 'register'">
            <span aria-current="page">Criar conta</span>
            <a href="/auth/login">Entrar</a>
          </template>
          <template v-else>
            <span aria-current="page">Entrar</span>
            <a href="/auth/register">Criar conta</a>
          </template>
        </nav>
        <slot />
      </section>
    </main>
  </div>
</template>

<style scoped>
.registration-shell{min-height:100vh;background:#f8f8fc;color:#181a2b;font-family:'DM Sans',Arial,sans-serif;padding:0 48px 48px;box-sizing:border-box}
.registration-header{max-width:1180px;margin:auto;display:flex;align-items:center;justify-content:space-between;gap:24px;padding:28px 0 30px}
.registration-brand{display:flex;align-items:center;gap:10px;font-family:Manrope,Arial,sans-serif;font-size:19px;font-weight:600;line-height:1.05;letter-spacing:-.8px;color:#181a2b;text-decoration:none}.registration-brand strong{display:block;font-size:24px;letter-spacing:-1px}.registration-brand strong span{color:#2420db}
.registration-logo{position:relative;display:block;width:44px;height:48px;overflow:hidden;flex-shrink:0}.registration-logo img{position:absolute;width:75px;max-width:none;left:-13px;top:-14px}
.registration-back{display:inline-flex;align-items:center;gap:8px;font-size:14px;color:#2420db;text-decoration:none}.registration-back svg{width:16px;height:16px}
.registration-layout{max-width:1180px;margin:auto;display:grid;grid-template-columns:1fr 1.04fr;gap:56px;align-items:start}
.registration-story{position:relative;min-height:690px;border-radius:24px;overflow:hidden;isolation:isolate;background:#091526;color:#fff}.registration-photo{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;object-position:84% top;z-index:-2}.registration-story:after{content:'';position:absolute;inset:0;z-index:-1;background:linear-gradient(0deg,#091526 0%,#091526ef 32%,#09152666 66%,#09152600)}
.registration-story-copy{position:absolute;bottom:44px;left:38px;right:30px}.registration-eyebrow{font-size:10px;font-weight:700;color:#c1bcff;letter-spacing:1.5px}.registration-story h2{font-family:Manrope,Arial,sans-serif;color:#fff;font-size:38px;font-weight:600;line-height:1.18;letter-spacing:-1.4px;margin:16px 0 0}.registration-story p{font-size:15px;line-height:1.75;max-width:350px;color:#d7dbe5;margin:20px 0 0}
.registration-palette{display:flex;gap:9px;margin-top:28px}.registration-palette i{width:14px;height:14px;border-radius:50%;background:#e54843}.registration-palette i:nth-child(2){background:#332fff}.registration-palette i:nth-child(3){background:#ffcf32}.registration-palette i:nth-child(4){background:#f3f4f6}.registration-palette i:nth-child(5){background:#24a46c}.registration-caption{display:block;margin-top:13px;color:#a9b4c7;font-size:8px;letter-spacing:1.6px}
.registration-form-panel{padding-top:20px;min-width:0}.registration-tabs{display:flex;gap:25px;border-bottom:1px solid #e3e4eb;padding-bottom:14px;margin-bottom:28px;font-size:14px}.registration-tabs span{font-weight:700;color:#2420db}.registration-tabs a{color:#646777;text-decoration:none}
a:focus-visible{outline:3px solid #2420db;outline-offset:5px;border-radius:3px}
.login-shell .registration-layout{align-items:center}.login-shell .registration-story{min-height:600px}.login-shell .registration-form-panel{width:100%;max-width:470px;justify-self:center;padding:30px 0}.login-shell .registration-tabs{margin-bottom:36px}
@media(max-width:800px){.registration-shell{padding:0 24px 40px}.registration-layout{grid-template-columns:1fr;gap:28px}.registration-header{padding:24px 0}.registration-story{min-height:285px;border-radius:16px}.registration-photo{object-position:84% 30%}.registration-story:after{background:linear-gradient(90deg,#091526f5,#091526b8 55%,#09152633)}.registration-story-copy{left:25px;right:25px;bottom:24px}.registration-story h2{font-size:29px;max-width:290px}.registration-story p{display:none}.registration-palette{margin-top:18px}.registration-caption{font-size:7px}.registration-form-panel{padding-top:0}.registration-back{font-size:12px}}
@media(max-width:420px){.registration-shell{padding-inline:16px}.registration-brand{font-size:15px}.registration-brand strong{font-size:19px}}
@media(max-width:800px){.login-shell .registration-story{min-height:230px}.login-shell .registration-story h2{font-size:27px}.login-shell .registration-form-panel{padding:0;max-width:none}.login-shell .registration-tabs{margin-bottom:26px}.login-shell .registration-story-copy{bottom:23px}}
</style>
