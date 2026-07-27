import{_ as F}from"./BackHeader.vue_vue_type_script_setup_true_lang-MUcQDAQP.js";import{_ as h}from"./AgentBottomNav.vue_vue_type_script_setup_true_lang-BYDyJJlc.js";/* empty css                                                                  */import{P as x,r as e,ai as y,aj as M,d as k,x as w,l as u,c as b,o as P,b as p,e as f,m as S,f as T}from"./agent-DnGcPoFm.js";const j=x("agentMessage",()=>{const i=e("SMS"),s=e([]),n=e(!1),t=e(null),o=e(1),d=e(1),m=e(0),g=e([{template_id:"TPL001",template_name:"보험만기안내",content:`[보험만기안내]
#{고객명} 고객님, 가입하신 #{보험상품명}의 만기일이 #{만기일}입니다.

만기 전 갱신 또는 새로운 상품 상담을 원하시면 아래 버튼을 눌러주세요.

감사합니다.`,category:"안내"},{template_id:"TPL002",template_name:"상담예약확인",content:`[상담예약확인]
#{고객명} 고객님, 상담 예약이 확정되었습니다.

- 일시: #{상담일시}
- 장소: #{상담장소}
- 담당: #{설계사명}

변경이 필요하시면 연락 부탁드립니다.`,category:"예약"},{template_id:"TPL003",template_name:"계약체결안내",content:`[계약체결안내]
#{고객명} 고객님, 보험 계약이 정상적으로 체결되었습니다.

- 상품명: #{보험상품명}
- 보험료: #{보험료}원/월
- 보장개시일: #{보장개시일}

보험증권은 별도로 발송됩니다.
감사합니다.`,category:"계약"}]);async function v(c){n.value=!0,t.value=null;try{const a=await y(c);return s.value.unshift(a.data.data),a.data.data}catch(a){const l=a?.response?.data?.message;throw t.value=l||"메시지 발송에 실패했습니다.",a}finally{n.value=!1}}async function _(c){n.value=!0,t.value=null;try{const a={page:o.value,...c},r=(await M(a)).data.data;s.value=r.data,o.value=r.current_page,d.value=r.last_page,m.value=r.total}catch(a){const l=a?.response?.data?.message;t.value=l||"메시지 내역을 불러오는데 실패했습니다."}finally{n.value=!1}}return{sendMethod:i,messageHistory:s,kakaoTemplates:g,loading:n,error:t,currentPage:o,lastPage:d,total:m,send:v,loadHistory:_}}),B={class:"min-h-screen bg-gradient-to-b from-[#FFF3ED] to-[#FFFFFF] flex justify-center"},I={class:"w-full max-w-[402px] h-screen relative bg-gradient-to-b from-[#FFF3ED] to-[#FFFFFF]"},V={class:"px-6 py-3 overflow-y-auto pb-20",style:{height:"calc(100dvh - 56px - 60px)"}},C=k({__name:"MessageSendView",setup(i){const s=j();e(null),e(!1),w(()=>{s.loadHistory()}),u(()=>["선택하세요",...s.kakaoTemplates.map(t=>t.template_name)]),u(()=>s.kakaoTemplates.find(t=>t.template_name===n.value.templateId)),e({receiverId:"",content:"",imageFile:null,imagePreview:""});const n=e({templateId:"선택하세요",receiverId:""});return e({receiverId:"",title:"",content:""}),(t,o)=>(P(),b("div",B,[p("div",I,[f(F,{title:"메시지 발송"}),p("main",V,[o[14]||(o[14]=S('<div class="flex flex-col items-center justify-center py-20"><div class="w-16 h-16 mx-auto mb-4 rounded-full bg-[#FFF0E5] flex items-center justify-center"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#FF7B22" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"></path></svg></div><h3 class="text-[18px] font-bold text-[#333] mb-2">업데이트 진행중</h3><p class="text-[14px] text-[#999] leading-relaxed text-center"> 메시지 발송 기능을 개선하고 있습니다.<br> 빠른 시일 내에 업데이트될 예정입니다. </p></div>',1)),T("",!0)]),f(h)])]))}});export{C as default};
