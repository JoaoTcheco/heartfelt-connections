import { createFileRoute } from "@tanstack/react-router";
import {
  ArrowRight,
  CalendarDays,
  Check,
  ChevronRight,
  Clock3,
  Heart,
  Instagram,
  MapPin,
  Menu,
  Phone,
  Sparkles,
  Star,
  X,
} from "lucide-react";
import { useState } from "react";

export const Route = createFileRoute("/")({
  component: BeautyHome,
});

const services = [
  { title: "Corte & Styling", description: "Cortes personalizados, escova e finalizações para realçar o seu estilo.", price: "desde 850 MT" },
  { title: "Coloração", description: "Tons, mechas e técnicas de cor pensadas para valorizar a sua beleza.", price: "desde 1.500 MT" },
  { title: "Manicure & Pedicure", description: "Cuidado completo para mãos e pés, com acabamento elegante e delicado.", price: "desde 500 MT" },
  { title: "Make-up", description: "Maquilhagem para o dia a dia, eventos e ocasiões especiais.", price: "desde 900 MT" },
];

const gallery = [
  "https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?auto=format&fit=crop&w=900&q=85",
  "https://images.unsplash.com/photo-1600948836101-f9ffda59d250?auto=format&fit=crop&w=900&q=85",
  "https://images.unsplash.com/photo-1487412912498-0447578fcca8?auto=format&fit=crop&w=900&q=85",
];

function BeautyHome() {
  const [menuOpen, setMenuOpen] = useState(false);

  const closeMenu = () => setMenuOpen(false);

  return (
    <div className="min-h-screen overflow-x-hidden bg-[#fffaf7] text-[#302521]">
      <header className="fixed inset-x-0 top-0 z-50 border-b border-[#eadbd4]/70 bg-[#fffaf7]/90 backdrop-blur-xl">
        <div className="mx-auto flex h-20 max-w-7xl items-center justify-between px-5 lg:px-8">
          <a href="#inicio" onClick={closeMenu} className="flex items-center gap-2">
            <div className="flex h-10 w-10 items-center justify-center rounded-full bg-[#a85c63] text-white shadow-sm">
              <Sparkles size={18} />
            </div>
            <div>
              <p className="font-serif text-xl font-semibold tracking-wide">BELLEYA</p>
              <p className="text-[9px] uppercase tracking-[0.35em] text-[#9a7771]">Beauty Studio</p>
            </div>
          </a>

          <nav className="hidden items-center gap-8 text-sm font-medium text-[#6e5751] lg:flex">
            <a href="#inicio" className="transition hover:text-[#a85c63]">Início</a>
            <a href="#servicos" className="transition hover:text-[#a85c63]">Serviços</a>
            <a href="#sobre" className="transition hover:text-[#a85c63]">Sobre nós</a>
            <a href="#galeria" className="transition hover:text-[#a85c63]">Galeria</a>
            <a href="#contactos" className="transition hover:text-[#a85c63]">Contactos</a>
          </nav>

          <a href="#agendar" className="hidden rounded-full bg-[#a85c63] px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-[#a85c63]/15 transition hover:-translate-y-0.5 hover:bg-[#914e56] lg:inline-flex">
            Agendar horário
          </a>

          <button
            type="button"
            aria-label="Abrir menu"
            className="rounded-full border border-[#eadbd4] p-2 lg:hidden"
            onClick={() => setMenuOpen((v) => !v)}
          >
            {menuOpen ? <X size={21} /> : <Menu size={21} />}
          </button>
        </div>

        {menuOpen && (
          <div className="border-t border-[#eadbd4] bg-[#fffaf7] px-5 py-5 lg:hidden">
            <nav className="mx-auto flex max-w-7xl flex-col gap-4 text-sm font-medium">
              {["Início", "Serviços", "Sobre nós", "Galeria", "Contactos"].map((item) => (
                <a key={item} href={`#${item === "Início" ? "inicio" : item.toLowerCase().replace(" ", "-")}`} onClick={closeMenu}>
                  {item}
                </a>
              ))}
              <a href="#agendar" onClick={closeMenu} className="mt-2 inline-flex justify-center rounded-full bg-[#a85c63] px-5 py-3 font-semibold text-white">
                Agendar horário
              </a>
            </nav>
          </div>
        )}
      </header>

      <main>
        <section id="inicio" className="relative flex min-h-[760px] items-center pt-20">
          <div className="absolute inset-0 bg-[radial-gradient(circle_at_20%_30%,rgba(218,174,165,0.28),transparent_34%),radial-gradient(circle_at_90%_20%,rgba(244,222,213,0.7),transparent_30%)]" />
          <div className="mx-auto grid max-w-7xl items-center gap-14 px-5 py-20 lg:grid-cols-[1.02fr_.98fr] lg:px-8">
            <div className="relative z-10 max-w-2xl">
              <div className="mb-7 inline-flex items-center gap-2 rounded-full border border-[#e5cfc8] bg-white/70 px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-[#a85c63]">
                <Sparkles size={14} /> Beleza que combina consigo
              </div>
              <h1 className="font-serif text-5xl font-semibold leading-[1.02] tracking-tight text-[#302521] sm:text-6xl lg:text-7xl">
                O seu momento de <span className="italic text-[#a85c63]">beleza</span> começa aqui.
              </h1>
              <p className="mt-7 max-w-xl text-lg leading-8 text-[#735f59]">
                Um espaço pensado para cuidar de si, elevar a sua autoestima e transformar cada visita numa experiência especial.
              </p>
              <div className="mt-9 flex flex-col gap-3 sm:flex-row">
                <a href="#agendar" className="inline-flex items-center justify-center gap-2 rounded-full bg-[#a85c63] px-7 py-4 font-semibold text-white shadow-xl shadow-[#a85c63]/15 transition hover:-translate-y-0.5 hover:bg-[#914e56]">
                  Marcar atendimento <ArrowRight size={17} />
                </a>
                <a href="#servicos" className="inline-flex items-center justify-center gap-2 rounded-full border border-[#d9c1b9] bg-white/60 px-7 py-4 font-semibold text-[#5c4640] transition hover:bg-white">
                  Ver serviços
                </a>
              </div>
              <div className="mt-10 flex flex-wrap gap-6 text-sm text-[#806b64]">
                <span className="flex items-center gap-2"><Check size={16} className="text-[#a85c63]" /> Atendimento personalizado</span>
                <span className="flex items-center gap-2"><Check size={16} className="text-[#a85c63]" /> Profissionais especializados</span>
              </div>
            </div>

            <div className="relative mx-auto w-full max-w-xl">
              <div className="absolute -left-6 -top-6 h-24 w-24 rounded-full border border-[#d9b9b0]" />
              <div className="absolute -bottom-7 -right-5 h-32 w-32 rounded-full bg-[#ead1c8]/70" />
              <div className="relative overflow-hidden rounded-[2rem] shadow-2xl shadow-[#6d4a43]/15">
                <img
                  src="https://images.unsplash.com/photo-1560066984-138dadb4c035?auto=format&fit=crop&w=1200&q=90"
                  alt="Interior elegante de um salão de beleza"
                  className="h-[590px] w-full object-cover"
                />
                <div className="absolute bottom-5 left-5 right-5 flex items-center justify-between rounded-2xl border border-white/30 bg-white/85 p-4 backdrop-blur-md">
                  <div>
                    <p className="text-xs uppercase tracking-[0.18em] text-[#9a7771]">Experiência Belleya</p>
                    <p className="mt-1 font-serif text-lg font-semibold">Cuidar de si é essencial.</p>
                  </div>
                  <div className="flex items-center gap-1 text-[#a85c63]"><Star size={15} fill="currentColor" /><span className="text-sm font-bold">4.9</span></div>
                </div>
              </div>
            </div>
          </div>
        </section>

        <section id="servicos" className="bg-white px-5 py-24 lg:px-8">
          <div className="mx-auto max-w-7xl">
            <div className="flex flex-col justify-between gap-5 md:flex-row md:items-end">
              <div>
                <p className="text-xs font-bold uppercase tracking-[0.3em] text-[#a85c63]">Os nossos serviços</p>
                <h2 className="mt-3 font-serif text-4xl font-semibold sm:text-5xl">Cuidados pensados para si.</h2>
              </div>
              <p className="max-w-md text-sm leading-6 text-[#806b64]">Do cuidado diário aos grandes momentos, criamos serviços personalizados para cada necessidade.</p>
            </div>

            <div className="mt-12 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
              {services.map((service, index) => (
                <article key={service.title} className="group rounded-3xl border border-[#eadbd4] bg-[#fffaf7] p-7 transition duration-300 hover:-translate-y-1 hover:border-[#d6b2aa] hover:shadow-xl hover:shadow-[#8b5b54]/8">
                  <div className="flex items-center justify-between">
                    <span className="font-serif text-3xl text-[#d7aaa1]">0{index + 1}</span>
                    <ChevronRight size={18} className="text-[#a85c63] transition group-hover:translate-x-1" />
                  </div>
                  <h3 className="mt-10 font-serif text-2xl font-semibold">{service.title}</h3>
                  <p className="mt-3 min-h-20 text-sm leading-6 text-[#806b64]">{service.description}</p>
                  <p className="mt-6 text-sm font-bold text-[#a85c63]">{service.price}</p>
                </article>
              ))}
            </div>
          </div>
        </section>

        <section id="sobre" className="bg-[#f6ebe6] px-5 py-24 lg:px-8">
          <div className="mx-auto grid max-w-7xl items-center gap-14 lg:grid-cols-2">
            <div className="relative">
              <img
                src="https://images.unsplash.com/photo-1559599101-f09722fb4948?auto=format&fit=crop&w=1000&q=90"
                alt="Profissional de beleza a atender uma cliente"
                className="h-[540px] w-full rounded-[2rem] object-cover"
              />
              <div className="absolute -bottom-6 -right-4 rounded-2xl bg-white p-5 shadow-xl sm:right-6">
                <p className="font-serif text-3xl font-semibold text-[#a85c63]">+5</p>
                <p className="text-xs uppercase tracking-wider text-[#806b64]">anos a cuidar de si</p>
              </div>
            </div>
            <div>
              <p className="text-xs font-bold uppercase tracking-[0.3em] text-[#a85c63]">Sobre a Belleya</p>
              <h2 className="mt-4 font-serif text-4xl font-semibold leading-tight sm:text-5xl">Mais do que um salão. Um espaço para se sentir bem.</h2>
              <p className="mt-6 leading-7 text-[#735f59]">Acreditamos que beleza e bem-estar caminham juntos. Por isso, cada detalhe da nossa experiência foi pensado para que possa desacelerar, cuidar de si e sair daqui a sentir-se ainda melhor.</p>
              <div className="mt-8 grid gap-5 sm:grid-cols-2">
                <div><Heart className="text-[#a85c63]" size={21} /><p className="mt-3 font-semibold">Cuidado genuíno</p><p className="mt-1 text-sm leading-6 text-[#806b64]">Escutamos o que procura e adaptamos cada serviço.</p></div>
                <div><Sparkles className="text-[#a85c63]" size={21} /><p className="mt-3 font-semibold">Detalhes que contam</p><p className="mt-1 text-sm leading-6 text-[#806b64]">Qualidade, higiene e atenção em cada etapa.</p></div>
              </div>
            </div>
          </div>
        </section>

        <section id="galeria" className="bg-white px-5 py-24 lg:px-8">
          <div className="mx-auto max-w-7xl">
            <div className="text-center">
              <p className="text-xs font-bold uppercase tracking-[0.3em] text-[#a85c63]">Inspiração</p>
              <h2 className="mt-3 font-serif text-4xl font-semibold sm:text-5xl">Um pouco do nosso universo.</h2>
            </div>
            <div className="mt-12 grid gap-5 md:grid-cols-3">
              {gallery.map((src, index) => (
                <div key={src} className={`overflow-hidden rounded-[2rem] ${index === 1 ? "md:mt-10" : ""}`}>
                  <img src={src} alt={`Inspiração de beleza ${index + 1}`} className="h-[420px] w-full object-cover transition duration-500 hover:scale-105" />
                </div>
              ))}
            </div>
          </div>
        </section>

        <section className="bg-[#302521] px-5 py-24 text-white lg:px-8">
          <div className="mx-auto grid max-w-7xl gap-10 lg:grid-cols-[1fr_auto] lg:items-center">
            <div>
              <div className="flex items-center gap-1 text-[#e7b7ae]"><Star size={17} fill="currentColor" /><Star size={17} fill="currentColor" /><Star size={17} fill="currentColor" /><Star size={17} fill="currentColor" /><Star size={17} fill="currentColor" /></div>
              <blockquote className="mt-5 max-w-3xl font-serif text-3xl leading-tight sm:text-4xl">“Saí de lá a sentir-me outra pessoa. Atendimento impecável, ambiente lindo e um resultado que superou as minhas expectativas.”</blockquote>
              <p className="mt-6 text-sm text-[#cdbab4]">— Cliente Belleya</p>
            </div>
            <div className="rounded-3xl border border-white/10 bg-white/5 p-6">
              <p className="text-xs uppercase tracking-[0.25em] text-[#cdbab4]">Atendimento</p>
              <div className="mt-4 flex items-center gap-3"><Clock3 size={18} className="text-[#e7b7ae]" /><span>Seg — Sáb · 08:00 — 19:00</span></div>
              <div className="mt-3 flex items-center gap-3"><MapPin size={18} className="text-[#e7b7ae]" /><span>Maputo, Moçambique</span></div>
            </div>
          </div>
        </section>

        <section id="agendar" className="px-5 py-24 lg:px-8">
          <div className="mx-auto max-w-5xl overflow-hidden rounded-[2rem] bg-[#e7d0c8] px-7 py-14 text-center sm:px-12">
            <p className="text-xs font-bold uppercase tracking-[0.3em] text-[#8e535a]">Reserve o seu momento</p>
            <h2 className="mt-4 font-serif text-4xl font-semibold sm:text-5xl">Pronta para se sentir ainda mais bonita?</h2>
            <p className="mx-auto mt-5 max-w-xl leading-7 text-[#674f49]">Escolha o seu serviço e entre em contacto connosco para encontrar o melhor horário.</p>
            <div className="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
              <a href="tel:+258840000000" className="inline-flex items-center justify-center gap-2 rounded-full bg-[#a85c63] px-7 py-4 font-semibold text-white transition hover:bg-[#914e56]"><Phone size={17} /> Ligar agora</a>
              <a href="https://wa.me/258840000000" target="_blank" rel="noreferrer" className="inline-flex items-center justify-center gap-2 rounded-full bg-white px-7 py-4 font-semibold text-[#5c4640] transition hover:bg-[#fffaf7]"><CalendarDays size={17} /> Agendar pelo WhatsApp</a>
            </div>
          </div>
        </section>
      </main>

      <footer id="contactos" className="border-t border-[#eadbd4] bg-[#fffaf7] px-5 py-12 lg:px-8">
        <div className="mx-auto flex max-w-7xl flex-col gap-8 md:flex-row md:items-end md:justify-between">
          <div>
            <div className="flex items-center gap-2">
              <div className="flex h-9 w-9 items-center justify-center rounded-full bg-[#a85c63] text-white"><Sparkles size={16} /></div>
              <span className="font-serif text-xl font-semibold">BELLEYA</span>
            </div>
            <p className="mt-4 max-w-sm text-sm leading-6 text-[#806b64]">Beleza, cuidado e confiança num só lugar.</p>
          </div>
          <div className="flex items-center gap-3">
            <a aria-label="Instagram" href="#" className="rounded-full border border-[#dec9c2] p-3 transition hover:bg-white"><Instagram size={18} /></a>
            <a aria-label="Telefone" href="tel:+258840000000" className="rounded-full border border-[#dec9c2] p-3 transition hover:bg-white"><Phone size={18} /></a>
            <a aria-label="Localização" href="#contactos" className="rounded-full border border-[#dec9c2] p-3 transition hover:bg-white"><MapPin size={18} /></a>
          </div>
        </div>
        <div className="mx-auto mt-10 max-w-7xl border-t border-[#eadbd4] pt-6 text-xs text-[#967f78]">© 2026 Belleya Beauty Studio. Todos os direitos reservados.</div>
      </footer>
    </div>
  );
}
