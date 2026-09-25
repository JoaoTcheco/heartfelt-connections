; Instalador do FarmaPonto para Windows 64 bits.
; Gerado com NSIS 3. Instala o programa em Program Files, cria atalhos,
; registra a entrada em "Aplicacoes e funcionalidades" e inclui desinstalador.
; O codigo-fonte do sistema viaja dentro de cofre.dat (AES-256-CBC) cuja chave
; deriva do proprio FarmaPonto.exe instalado: extrair o instalador com
; ferramentas de arquivo nao devolve codigo PHP legivel.

Unicode true
ManifestDPIAware true
SetCompressor /SOLID lzma
SetCompressorDictSize 64

!define NOME "FarmaPonto"
!define VERSAO "1.0.0"
!define EMPRESA "FarmaPonto"
!define CHAVE_REG "Software\Microsoft\Windows\CurrentVersion\Uninstall\FarmaPonto"

Name "${NOME} ${VERSAO}"
BrandingText "${NOME} ${VERSAO} - funciona sem Internet"
OutFile "${SAIDA}\FarmaPonto-Setup-${VERSAO}.exe"
InstallDir "$PROGRAMFILES64\${NOME}"
InstallDirRegKey HKLM "Software\${NOME}" "InstallDir"
RequestExecutionLevel admin
ShowInstDetails show
ShowUninstDetails show

!include "MUI2.nsh"
!include "LogicLib.nsh"
!include "FileFunc.nsh"

!define MUI_ABORTWARNING
!define MUI_COMPONENTSPAGE_NODESC
!define MUI_ICON "${RECURSOS}\icone.ico"
!define MUI_UNICON "${RECURSOS}\icone.ico"
!define MUI_FINISHPAGE_RUN "$INSTDIR\FarmaPonto.exe"
!define MUI_FINISHPAGE_RUN_TEXT "Abrir o FarmaPonto agora"
!define MUI_WELCOMEPAGE_TITLE "Instalar o ${NOME}"
!define MUI_WELCOMEPAGE_TEXT "Este assistente instala o ${NOME} neste computador.$\r$\n$\r$\nO programa funciona totalmente sem Internet. Os dados da farmacia ficam guardados apenas neste computador, na pasta pessoal do utilizador.$\r$\n$\r$\nClique em Avancar para continuar."

!insertmacro MUI_PAGE_WELCOME
!insertmacro MUI_PAGE_LICENSE "${RECURSOS}\licenca.txt"
!insertmacro MUI_PAGE_COMPONENTS
!insertmacro MUI_PAGE_DIRECTORY
!insertmacro MUI_PAGE_INSTFILES
!insertmacro MUI_PAGE_FINISH

!insertmacro MUI_UNPAGE_CONFIRM
!insertmacro MUI_UNPAGE_INSTFILES

!insertmacro MUI_LANGUAGE "PortugueseBR"

Function .onInit
  ; Impedir duas instalacoes simultaneas
  System::Call 'kernel32::CreateMutex(p 0, i 0, t "FarmaPontoInstalador") p .r1 ?e'
  Pop $R0
  ${If} $R0 != 0
    MessageBox MB_OK|MB_ICONEXCLAMATION "O instalador do ${NOME} ja esta aberto."
    Abort
  ${EndIf}

  ; Fechar o programa se estiver a correr
  nsExec::Exec 'taskkill /IM FarmaPonto.exe /F'
  Pop $R0
FunctionEnd

Section "Programa" SEC_PROGRAMA
  SectionIn RO
  SetOutPath "$INSTDIR"
  SetOverwrite on
  File /r "${APP}\*.*"

  WriteRegStr HKLM "Software\${NOME}" "InstallDir" "$INSTDIR"
  WriteRegStr HKLM "Software\${NOME}" "Versao" "${VERSAO}"

  ; Entrada em Aplicacoes e funcionalidades
  WriteUninstaller "$INSTDIR\Desinstalar.exe"
  WriteRegStr HKLM "${CHAVE_REG}" "DisplayName" "${NOME} ${VERSAO}"
  WriteRegStr HKLM "${CHAVE_REG}" "DisplayVersion" "${VERSAO}"
  WriteRegStr HKLM "${CHAVE_REG}" "Publisher" "${EMPRESA}"
  WriteRegStr HKLM "${CHAVE_REG}" "DisplayIcon" "$INSTDIR\FarmaPonto.exe"
  WriteRegStr HKLM "${CHAVE_REG}" "UninstallString" '"$INSTDIR\Desinstalar.exe"'
  WriteRegStr HKLM "${CHAVE_REG}" "QuietUninstallString" '"$INSTDIR\Desinstalar.exe" /S'
  WriteRegStr HKLM "${CHAVE_REG}" "InstallLocation" "$INSTDIR"
  WriteRegDWORD HKLM "${CHAVE_REG}" "NoModify" 1
  WriteRegDWORD HKLM "${CHAVE_REG}" "NoRepair" 1
  ${GetSize} "$INSTDIR" "/S=0K" $0 $1 $2
  IntFmt $0 "0x%08X" $0
  WriteRegDWORD HKLM "${CHAVE_REG}" "EstimatedSize" "$0"
SectionEnd

Section "Atalho no Menu Iniciar" SEC_MENU
  CreateDirectory "$SMPROGRAMS\${NOME}"
  CreateShortCut "$SMPROGRAMS\${NOME}\${NOME}.lnk" "$INSTDIR\FarmaPonto.exe" "" "$INSTDIR\FarmaPonto.exe" 0
  CreateShortCut "$SMPROGRAMS\${NOME}\Desinstalar ${NOME}.lnk" "$INSTDIR\Desinstalar.exe"
SectionEnd

Section "Atalho no Ambiente de Trabalho" SEC_AREA
  CreateShortCut "$DESKTOP\${NOME}.lnk" "$INSTDIR\FarmaPonto.exe" "" "$INSTDIR\FarmaPonto.exe" 0
SectionEnd

!insertmacro MUI_FUNCTION_DESCRIPTION_BEGIN
  !insertmacro MUI_DESCRIPTION_TEXT ${SEC_PROGRAMA} "Ficheiros do programa (obrigatorio)."
  !insertmacro MUI_DESCRIPTION_TEXT ${SEC_MENU} "Cria atalhos no Menu Iniciar."
  !insertmacro MUI_DESCRIPTION_TEXT ${SEC_AREA} "Cria um atalho no Ambiente de Trabalho."
!insertmacro MUI_FUNCTION_DESCRIPTION_END

Function un.onInit
  nsExec::Exec 'taskkill /IM FarmaPonto.exe /F'
  Pop $R0
FunctionEnd

Section "Uninstall"
  Delete "$DESKTOP\${NOME}.lnk"
  Delete "$SMPROGRAMS\${NOME}\${NOME}.lnk"
  Delete "$SMPROGRAMS\${NOME}\Desinstalar ${NOME}.lnk"
  RMDir "$SMPROGRAMS\${NOME}"

  RMDir /r "$INSTDIR\locales"
  RMDir /r "$INSTDIR\resources"
  Delete "$INSTDIR\*.dll"
  Delete "$INSTDIR\*.exe"
  Delete "$INSTDIR\*.pak"
  Delete "$INSTDIR\*.bin"
  Delete "$INSTDIR\*.dat"
  Delete "$INSTDIR\*.json"
  Delete "$INSTDIR\*.html"
  Delete "$INSTDIR\LICENSE"
  Delete "$INSTDIR\version"
  RMDir "$INSTDIR"

  DeleteRegKey HKLM "${CHAVE_REG}"
  DeleteRegKey HKLM "Software\${NOME}"

  ; Os dados da farmacia ficam intactos por seguranca
  MessageBox MB_OK|MB_ICONINFORMATION "O ${NOME} foi removido.$\r$\n$\r$\nOs dados da farmacia continuam guardados em:$\r$\n$APPDATA\${NOME}$\r$\n$\r$\nApague essa pasta manualmente se quiser eliminar tambem o historico."
SectionEnd
