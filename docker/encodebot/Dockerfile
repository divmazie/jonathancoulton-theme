FROM ubuntu:14.04.2

# https://trac.ffmpeg.org/wiki/CompilationGuide/Ubuntu
RUN DEBIAN_FRONTEND=noninteractive apt-get update &&  DEBIAN_FRONTEND=noninteractive apt-get install -y --force-yes \
    autoconf automake build-essential libass-dev libfreetype6-dev \
    libsdl1.2-dev libtheora-dev libtool libva-dev libvdpau-dev libvorbis-dev libxcb1-dev libxcb-shm0-dev \
    libxcb-xfixes0-dev pkg-config texi2html zlib1g-dev

RUN mkdir -p ~/ffmpeg_sources ~/ffmpeg_build ~/bin

# yasm speeds up the resultant ffmpeg install
# If your repository offers a yasm package ≥ 1.2.0 then you can install that instead of compiling
# wget needed to get packages
RUN  DEBIAN_FRONTEND=noninteractive apt-get install -y --force-yes \
    wget yasm nasm


# We'll build all the libs related to audio from source
# video not included

# build libfdk-aac
RUN cd ~/ffmpeg_sources && \
    wget -O fdk-aac.tar.gz https://github.com/mstorsjo/fdk-aac/tarball/master && \
    tar xzvf fdk-aac.tar.gz && \
    cd mstorsjo-fdk-aac* && \
    autoreconf -fiv && \
    ./configure --prefix="$HOME/ffmpeg_build" --disable-shared && \
    make && make install && make distclean

#libmp3lame
RUN sudo apt-get install nasm &&\
    cd ~/ffmpeg_sources &&\
    wget http://downloads.sourceforge.net/project/lame/lame/3.99/lame-3.99.5.tar.gz &&\
    tar xzvf lame-3.99.5.tar.gz &&\
    cd lame-3.99.5 &&\
    ./configure --prefix="$HOME/ffmpeg_build" --enable-nasm --disable-shared &&\
    make &&\
    make install &&\
    make distclean

# libopus
RUN cd ~/ffmpeg_sources &&\
    wget http://downloads.xiph.org/releases/opus/opus-1.1.tar.gz &&\
    tar xzvf opus-1.1.tar.gz &&\
    cd opus-1.1 &&\
    ./configure --prefix="$HOME/ffmpeg_build" --disable-shared &&\
    make &&\
    make install &&\
    make distclean


# build ffmpeg itself
RUN cd ~/ffmpeg_sources && \
    wget http://ffmpeg.org/releases/ffmpeg-snapshot.tar.bz2 && \
    tar xjvf ffmpeg-snapshot.tar.bz2;

WORKDIR /root/ffmpeg_sources/ffmpeg

RUN pwd
RUN ls -la

RUN PATH="$HOME/bin:$PATH" PKG_CONFIG_PATH="$HOME/ffmpeg_build/lib/pkgconfig" ./configure \
      --prefix="$HOME/ffmpeg_build" \
      --pkg-config-flags="--static" \
      --extra-cflags="-I$HOME/ffmpeg_build/include" \
      --extra-ldflags="-L$HOME/ffmpeg_build/lib" \
      --bindir="$HOME/bin" \
      --enable-gpl \
      --enable-libass \
      --enable-libfdk-aac \
      --enable-libfreetype \
      --enable-libmp3lame \
      --enable-libopus \
      --enable-libtheora \
      --enable-libvorbis \
#      --enable-libvpx \
#      --enable-libx264 \
#      --enable-libx265 \
      --enable-nonfree

RUN make
RUN make install
RUN make distclean
RUN hash -r

# cleanup
RUN mv ~/bin/* /usr/local/bin && rm -rf ~/*

# END INSTALL FFMPEG

WORKDIR /root/
RUN  DEBIAN_FRONTEND=noninteractive apt-get install -y --force-yes \
    php5 php5-cli

RUN  DEBIAN_FRONTEND=noninteractive apt-get install -y --force-yes \
     flac vorbis-tools gpac


RUN mkdir ~/encodes
WORKDIR /root/encodes


CMD php -d display_errors /root/src/proc2.php